<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const REMEMBER_COOKIE = 'auth_remember_30d';
    private const REMEMBER_DAYS = 30;
    private const FORGOT_PASSWORD_RATE_LIMIT = 3;
    private const FORGOT_PASSWORD_DECAY_SECONDS = 900;

    public function loginForm(Request $request)
    {
        if (session()->get('auth.logged_in')) {
            return session()->get('auth.role') === 'admin'
                ? redirect()->route('dashboard')
                : redirect()->route('portal.index');
        }

        $rememberedUser = $this->restoreFromRememberCookie($request);
        if ($rememberedUser) {
            return ($rememberedUser->role ?? 'user') === 'admin'
                ? redirect()->route('dashboard')
                : redirect()->route('portal.index');
        }

        return view('adminlogin');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $credentials['username'];
        $user = User::where('name', $identifier)->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['username' => 'Invalid login credentials.'])
                ->withInput();
        }

        if (($user->status ?? 'active') !== 'active') {
            return back()
                ->withErrors(['username' => 'This account is inactive. Please contact an administrator.'])
                ->withInput();
        }

        $remember = $request->boolean('remember-me') || $request->boolean('stay_logged_in');

        $request->session()->regenerate();
        $request->session()->put('auth.user_id', $user->id);
        $request->session()->put('auth.role', $user->role ?? 'user');
        $request->session()->put('auth.logged_in', true);
        $request->session()->put('auth.verified_at', now()->toISOString());
        $request->session()->put('auth.remember_me', $remember);

        $this->recordAudit($request, $user, 'auth.login', [
            'remember_me' => $remember,
            'role' => $user->role ?? 'user',
        ]);

        $this->dispatchAdminLoginTelegram($user, $request);

        if ($remember) {
            $plainToken = Str::random(64);
            $user->forceFill([
                'remember_token' => hash('sha256', $plainToken),
            ])->save();

            cookie()->queue(cookie(
                self::REMEMBER_COOKIE,
                $user->id.'|'.$plainToken,
                self::REMEMBER_DAYS * 24 * 60
            ));
        } else {
            $user->forceFill([
                'remember_token' => null,
            ])->save();

            cookie()->queue(cookie()->forget(self::REMEMBER_COOKIE));
        }

        return $request->session()->get('auth.role') === 'admin'
            ? redirect()->route('dashboard')
            : redirect()->route('portal.index');
    }

    public function twoFactorForm()
    {
        return redirect()->route('auth.login');
    }

    public function verify(Request $request)
    {
        return redirect()->route('auth.login');
    }

    public function resend(Request $request)
    {
        return redirect()->route('auth.login');
    }

    public function logout(Request $request)
    {
        $userId = $request->session()->get('auth.user_id');

        if (!$userId) {
            [$cookieUserId] = $this->parseRememberCookie((string) $request->cookie(self::REMEMBER_COOKIE));
            $userId = $cookieUserId;
        }

        if ($userId) {
            $user = User::find((int) $userId);
            if ($user) {
                $this->recordAudit($request, $user, 'auth.logout');

                $user->forceFill([
                    'remember_token' => null,
                ])->save();
            }
        }

        $request->session()->forget([
            'auth.logged_in',
            'auth.pending',
            'auth.user_id',
            'auth.verified_at',
            'auth.role',
            'auth.remember_me',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        cookie()->queue(cookie()->forget(self::REMEMBER_COOKIE));

        return redirect()->route('auth.login');
    }

    public function forgot()
    {
        return view('auth_forgot');
    }

    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = $this->forgotPasswordThrottleKey($request);
        if (RateLimiter::tooManyAttempts($throttleKey, self::FORGOT_PASSWORD_RATE_LIMIT)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'forgot' => "Too many reset requests. Try again in {$seconds} seconds.",
            ]);
        }

        $targetUser = $this->resolveResetTargetByUsername($validated['username']);
        if ($targetUser === null) {
            return back()
                ->withErrors([
                    'forgot' => 'No user account matches that username.',
                ])
                ->withInput();
        }

        $telegramNotifier = $this->resolveTelegramNotifier();
        if ($telegramNotifier === null || !method_exists($telegramNotifier, 'sendDirectMessage')) {
            return back()->withErrors([
                'forgot' => 'Telegram reset delivery is unavailable on this server.',
            ])->withInput();
        }

        $deliveryUser = $this->resolveResetDeliveryUser();
        if ($deliveryUser === null) {
            return back()->withErrors([
                'forgot' => 'No Telegram-enabled super admin account is configured to receive reset links.',
            ])->withInput();
        }

        $resetUrl = $this->buildResetUrlForUser($targetUser);
        $message = implode("\n", [
            'Password reset approval required.',
            "Requested username: {$validated['username']}",
            "Target account: {$targetUser->name}",
            "Approved by super admin recipient: {$deliveryUser->name}",
            "Reset link: {$resetUrl}",
            'This link expires in 60 minutes.',
        ]);

        $sent = false;
        try {
            $sent = (bool) $telegramNotifier->sendDirectMessage($deliveryUser, $message, [
                'event_type' => 'auth.password_reset_link',
                'ip' => $request->ip(),
                'username' => $targetUser->name,
                'target_user_id' => $targetUser->id,
                'delivery_user_id' => $deliveryUser->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('telegram password reset delivery failed', [
                'delivery_user_id' => $deliveryUser->id,
                'target_user_id' => $targetUser->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (!$sent) {
            return back()->withErrors([
                'forgot' => 'Could not send the Telegram reset link to the super admin account.',
            ])->withInput();
        }

        $this->recordAudit($request, $targetUser, 'auth.password_reset_link_requested', [
            'delivery' => 'telegram',
            'ip' => $request->ip(),
            'requested_username' => $validated['username'],
            'target_username' => $targetUser->name,
            'delivery_username' => $deliveryUser->name,
        ]);

        RateLimiter::hit($throttleKey, self::FORGOT_PASSWORD_DECAY_SECONDS);

        return redirect()
            ->route('auth.login')
            ->with('status', "Password reset link for {$targetUser->name} was sent to {$deliveryUser->name}'s Telegram bot.");
    }

    public function showResetForm(Request $request, string $token)
    {
        $username = trim((string) $request->query('username', ''));
        $user = $this->resolveResetTargetByUsername($username);

        abort_if($user === null || !Password::broker()->tokenExists($user, $token), 404);

        return view('auth_reset', [
            'token' => $token,
            'username' => $user->name,
        ]);
    }

    public function reset(Request $request)
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->resolveResetTargetByUsername($credentials['username']);
        if ($user === null || !Password::broker()->tokenExists($user, $credentials['token'])) {
            return back()
                ->withErrors(['username' => 'This reset link is not valid for the requested user account.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $user->forceFill([
            'password' => Hash::make($credentials['password']),
            'remember_token' => Str::random(60),
        ])->save();

        Password::broker()->deleteToken($user);

        $this->recordAudit($request, $user, 'auth.password_reset_completed', [
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('auth.login')
            ->with('status', 'Password reset complete. Sign in with the new password.');
    }

    public function requestAccess()
    {
        return response()->json([
            'status' => 'ok',
            'action' => 'auth.request_access',
        ]);
    }

    private function dispatchAdminLoginTelegram(User $user, Request $request): void
    {
        if (($user->role ?? 'user') !== 'admin') {
            return;
        }

        $telegramNotifier = $this->resolveTelegramNotifier();
        if ($telegramNotifier === null) {
            return;
        }

        try {
            $telegramNotifier->notifyUsersForEvent([
                'type' => 'auth.admin_login',
                'severity' => 'low',
                'device_id' => null,
                'device_name' => '-',
                'device_ip' => (string) ($request->ip() ?? '-'),
                'port' => '-',
                'message' => "Admin {$user->name} logged in.",
                'timestamp' => now()->toDateTimeString(),
                'data' => [
                    'user_id' => $user->id,
                    'username' => $user->name,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) ($request->userAgent() ?? ''), 0, 500),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('telegram auth.admin_login notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTelegramNotifier(): ?object
    {
        $serviceClass = 'App\\Services\\TelegramEventNotifier';
        if (!class_exists($serviceClass)) {
            return null;
        }

        try {
            $service = app($serviceClass);
            if (is_object($service) && method_exists($service, 'notifyUsersForEvent')) {
                return $service;
            }
        } catch (\Throwable $e) {
            Log::warning('telegram notifier unavailable in auth flow', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function forgotPasswordThrottleKey(Request $request): string
    {
        return 'auth:forgot-password:' . sha1((string) ($request->ip() ?? 'unknown'));
    }

    private function canReceiveTelegramResetLink(User $user): bool
    {
        return $user->telegram_enabled
            && trim((string) ($user->telegram_chat_id ?? '')) !== '';
    }

    private function buildResetUrlForUser(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('auth.reset', [
            'token' => $token,
            'username' => $user->name,
        ]);
    }

    private function resolveResetTargetByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        return User::where('name', $username)->first();
    }

    private function resolveResetDeliveryUser(): ?User
    {
        return User::query()
            ->where('role', 'admin')
            ->get()
            ->first(function (User $user): bool {
                return $user->isSuperAdmin() && $this->canReceiveTelegramResetLink($user);
            });
    }

    private function restoreFromRememberCookie(Request $request): ?User
    {
        [$userId, $token] = $this->parseRememberCookie((string) $request->cookie(self::REMEMBER_COOKIE));
        if (!$userId || !$token) {
            return null;
        }

        $user = User::find((int) $userId);
        if (!$user || ($user->status ?? 'active') !== 'active') {
            cookie()->queue(cookie()->forget(self::REMEMBER_COOKIE));
            return null;
        }

        $storedToken = (string) ($user->remember_token ?? '');
        if ($storedToken === '' || !hash_equals($storedToken, hash('sha256', $token))) {
            cookie()->queue(cookie()->forget(self::REMEMBER_COOKIE));
            return null;
        }

        $request->session()->regenerate();
        $request->session()->put('auth.user_id', $user->id);
        $request->session()->put('auth.role', $user->role ?? 'user');
        $request->session()->put('auth.logged_in', true);
        $request->session()->put('auth.verified_at', now()->toISOString());
        $request->session()->put('auth.remember_me', true);

        // Refresh cookie expiry on every successful restore.
        cookie()->queue(cookie(
            self::REMEMBER_COOKIE,
            $user->id.'|'.$token,
            self::REMEMBER_DAYS * 24 * 60
        ));

        return $user;
    }

    /**
     * @return array{0:int|null,1:string|null}
     */
    private function parseRememberCookie(?string $payload): array
    {
        if (!is_string($payload) || !str_contains($payload, '|')) {
            return [null, null];
        }

        [$userId, $token] = explode('|', $payload, 2);
        if (!ctype_digit($userId) || $token === '') {
            return [null, null];
        }

        return [(int) $userId, $token];
    }

    private function recordAudit(Request $request, User $user, string $action, array $metadata = []): void
    {
        try {
            AuditLog::create([
                'actor_id' => $user->id,
                'action' => $action,
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => $metadata,
                'ip_address' => substr((string) ($request->ip() ?? ''), 0, 45) ?: null,
                'user_agent' => substr((string) ($request->userAgent() ?? ''), 0, 1000) ?: null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('auth audit log write failed', [
                'action' => $action,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // 2FA helpers removed.
}
