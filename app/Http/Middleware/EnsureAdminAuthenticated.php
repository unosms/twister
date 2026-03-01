<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    private const REMEMBER_COOKIE = 'auth_remember_30d';

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $this->resolveAuthenticatedAdmin($request);

        if ($admin) {
            return $next($request);
        }

        $this->clearAuthState($request);

        return redirect()->route('auth.login')->withErrors([
            'username' => 'Please sign in as an admin to continue.',
        ]);
    }

    private function resolveAuthenticatedAdmin(Request $request): ?User
    {
        $loggedIn = $request->session()->get('auth.logged_in') === true;
        $userId = $request->session()->get('auth.user_id');

        if ($loggedIn && $userId) {
            $user = User::find($userId);
            if ($user && ($user->status ?? 'active') === 'active' && $user->role === 'admin') {
                return $user;
            }
        }

        return $this->restoreAdminFromRememberCookie($request);
    }

    private function restoreAdminFromRememberCookie(Request $request): ?User
    {
        $payload = $request->cookie(self::REMEMBER_COOKIE);
        if (!is_string($payload) || !str_contains($payload, '|')) {
            return null;
        }

        [$userId, $token] = explode('|', $payload, 2);
        if (!ctype_digit($userId) || $token === '') {
            return null;
        }

        $user = User::find((int) $userId);
        if (!$user || ($user->status ?? 'active') !== 'active' || $user->role !== 'admin') {
            return null;
        }

        $storedToken = (string) ($user->remember_token ?? '');
        if ($storedToken === '' || !hash_equals($storedToken, hash('sha256', $token))) {
            return null;
        }

        $request->session()->regenerate();
        $request->session()->put('auth.user_id', $user->id);
        $request->session()->put('auth.role', $user->role ?? 'user');
        $request->session()->put('auth.logged_in', true);
        $request->session()->put('auth.verified_at', now()->toISOString());
        $request->session()->put('auth.remember_me', true);

        // Refresh cookie expiry on use so activity keeps the user signed in.
        cookie()->queue(cookie(
            self::REMEMBER_COOKIE,
            $user->id.'|'.$token,
            30 * 24 * 60
        ));

        return $user;
    }

    private function clearAuthState(Request $request): void
    {
        $request->session()->forget([
            'auth.logged_in',
            'auth.pending',
            'auth.user_id',
            'auth.verified_at',
            'auth.role',
            'auth.remember_me',
        ]);

        cookie()->queue(cookie()->forget(self::REMEMBER_COOKIE));
    }
}
