<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditActivity
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->record($request);

        return $next($request);
    }

    private function record(Request $request): void
    {
        $userId = (int) $request->session()->get('auth.user_id', 0);
        if ($userId <= 0) {
            return;
        }

        $route = $request->route();
        $routeName = is_object($route) ? (string) ($route->getName() ?? '') : '';
        if ($this->shouldSkip($routeName)) {
            return;
        }

        $method = strtoupper($request->method());
        $path = trim((string) $request->path(), '/');
        $action = strtolower($method) . ' ' . ($routeName !== '' ? $routeName : ($path !== '' ? $path : '/'));

        $metadata = [
            'method' => $method,
            'route' => $routeName !== '' ? $routeName : null,
            'path' => '/' . $path,
            'query' => $this->sanitize((array) $request->query()),
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $input = $request->except([
                '_token',
                'password',
                'cisco_password',
                'enable_password',
                'telegram_bot_token',
            ]);
            $metadata['input'] = $this->sanitize($input);
        }

        try {
            AuditLog::create([
                'actor_id' => $userId,
                'action' => substr($action, 0, 255),
                'subject_type' => null,
                'subject_id' => null,
                'metadata' => $metadata,
                'ip_address' => substr((string) ($request->ip() ?? ''), 0, 45) ?: null,
                'user_agent' => substr((string) ($request->userAgent() ?? ''), 0, 1000) ?: null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Avoid breaking request flow if audit storage is unavailable.
        }
    }

    private function shouldSkip(string $routeName): bool
    {
        if ($routeName === '') {
            return false;
        }

        $skip = [
            'actions.dispatch',
            'devices.statusSnapshot',
            'devices.statusSnapshot.post',
            'debug.provisioning-log',
            'debug.provisioning-log.view',
        ];

        return in_array($routeName, $skip, true);
    }

    private function sanitize(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $result[(string) $key] = $this->sanitize($item);
                continue;
            }

            if (is_scalar($item) || $item === null) {
                $result[(string) $key] = is_string($item) ? substr($item, 0, 500) : $item;
            }
        }

        return $result;
    }
}

