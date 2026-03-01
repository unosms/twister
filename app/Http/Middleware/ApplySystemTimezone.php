<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemTimezone
{
    private const CACHE_KEY = 'system_settings.timezone';

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = $this->resolveTimezone();

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return $next($request);
    }

    private function resolveTimezone(): string
    {
        $defaultTimezone = (string) config('app.timezone', 'UTC');

        try {
            if (!AppSetting::supportsStorage()) {
                return $defaultTimezone;
            }

            $timezone = Cache::rememberForever(self::CACHE_KEY, function () use ($defaultTimezone): string {
                $storedTimezone = AppSetting::getValue('timezone', $defaultTimezone);

                return is_string($storedTimezone) && in_array($storedTimezone, timezone_identifiers_list(), true)
                    ? $storedTimezone
                    : $defaultTimezone;
            });

            return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
                ? $timezone
                : $defaultTimezone;
        } catch (\Throwable) {
            return $defaultTimezone;
        }
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
