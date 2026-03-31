<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Device;
use App\Models\DeviceEventPermission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramEventNotifier
{
    private const DEFAULT_SEVERITIES = ['high', 'critical'];
    private const DEFAULT_EVENT_TYPES = ['device.offline', 'port.down'];
    private const TIMEZONE_CACHE_KEY = 'system_settings.timezone';
    private const TELEGRAM_TEMPLATE_DEFAULT_SETTING = 'telegram_template_default';
    private const TELEGRAM_TEMPLATE_SEVERITY_SETTINGS = [
        'low' => 'telegram_template_low',
        'medium' => 'telegram_template_medium',
        'high' => 'telegram_template_high',
        'critical' => 'telegram_template_critical',
    ];
    private ?string $lastError = null;
    private ?array $globalTemplateConfig = null;

    public function notifyUsersForEvent(array $event): void
    {
        $event = $this->normalizeEvent($event);
        if ($event['type'] === '') {
            return;
        }

        $users = User::query()
            ->where('telegram_enabled', true)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get();

        foreach ($users as $user) {
            if (!$this->shouldNotify($user, $event)) {
                continue;
            }

            $chatIds = $this->extractChatIds($user);
            if (empty($chatIds)) {
                continue;
            }

            $botTokenOverride = trim((string) ($user->telegram_bot_token ?? ''));
            $botTokenOverride = $botTokenOverride !== '' ? $botTokenOverride : null;

            $message = $this->renderMessage($user, $event);
            foreach ($chatIds as $chatId) {
                $dedupeKey = $this->buildDedupeKey($user, $event, $chatId);
                if (!Cache::add($dedupeKey, 1, now()->addSeconds(60))) {
                    continue;
                }

                $this->sendTelegramMessage($chatId, $message, $botTokenOverride, [
                    'user_id' => $user->id,
                    'event_type' => $event['type'],
                    'severity' => $event['severity'],
                    'device_id' => $event['device_id'],
                    'chat_id' => $chatId,
                ]);
            }
        }
    }

    public function sendTestMessage(User $user): bool
    {
        $this->lastError = null;

        if (!($user->telegram_enabled ?? false)) {
            $this->lastError = 'Telegram notifications are disabled for this user.';
            return false;
        }

        $chatIds = $this->extractChatIds($user);
        if (empty($chatIds)) {
            $this->lastError = 'Telegram chat ID is missing.';
            return false;
        }

        $event = $this->normalizeEvent([
            'type' => 'system.test',
            'severity' => 'high',
            'device_id' => null,
            'device_name' => 'test-device',
            'device_ip' => '0.0.0.0',
            'port' => '80',
            'message' => 'This is a Telegram notification test message.',
            'timestamp' => now($this->resolveSystemTimezone())->toDateTimeString(),
        ]);

        $botTokenOverride = trim((string) ($user->telegram_bot_token ?? ''));
        $botTokenOverride = $botTokenOverride !== '' ? $botTokenOverride : null;

        $message = $this->renderMessage($user, $event);
        $sentAny = false;

        foreach ($chatIds as $chatId) {
            $sent = $this->sendTelegramMessage($chatId, $message, $botTokenOverride, [
                'user_id' => $user->id,
                'event_type' => 'system.test',
                'chat_id' => $chatId,
            ]);
            if ($sent) {
                $sentAny = true;
            }
        }

        return $sentAny;
    }

    public function sendDirectMessage(User $user, string $message, array $context = []): bool
    {
        $this->lastError = null;

        if (!($user->telegram_enabled ?? false)) {
            $this->lastError = 'Telegram notifications are disabled for this user.';
            return false;
        }

        $chatIds = $this->extractChatIds($user);
        if (empty($chatIds)) {
            $this->lastError = 'Telegram chat ID is missing.';
            return false;
        }

        $botTokenOverride = trim((string) ($user->telegram_bot_token ?? ''));
        $botTokenOverride = $botTokenOverride !== '' ? $botTokenOverride : null;

        $sentAny = false;
        foreach ($chatIds as $chatId) {
            $sent = $this->sendTelegramMessage($chatId, trim($message), $botTokenOverride, array_merge($context, [
                'user_id' => $user->id,
                'chat_id' => $chatId,
            ]));

            if ($sent) {
                $sentAny = true;
            }
        }

        return $sentAny;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function normalizeEvent(array $event): array
    {
        $rawType = strtolower(trim((string) ($event['type'] ?? $event['event_type'] ?? '')));
        $type = $this->canonicalizeEventType($rawType);
        $severityRaw = strtolower(trim((string) ($event['severity'] ?? 'high')));
        $severity = match ($severityRaw) {
            'critical', 'crit' => 'critical',
            'high' => 'high',
            'medium', 'average', 'warn', 'warning' => 'medium',
            default => 'low',
        };

        $deviceId = $event['device_id'] ?? $event['deviceId'] ?? null;
        $deviceId = is_numeric($deviceId) ? (int) $deviceId : null;

        $deviceName = (string) ($event['device_name'] ?? $event['deviceName'] ?? '');
        $deviceIp = (string) ($event['device_ip'] ?? $event['deviceIp'] ?? $event['ip'] ?? '');

        if (($deviceName === '' || $deviceIp === '') && $deviceId) {
            $device = Device::find($deviceId);
            if ($device) {
                $meta = is_array($device->metadata) ? $device->metadata : [];
                $deviceName = $deviceName !== '' ? $deviceName : (string) ($device->name ?? '');
                $deviceIp = $deviceIp !== '' ? $deviceIp : (string) (data_get($meta, 'cisco.ip_address', $device->ip_address ?? ''));
            }
        }

        $timestamp = $this->normalizeEventTimestamp($event['timestamp'] ?? null);
        $port = $event['port'] ?? $event['ifName'] ?? $event['interface'] ?? null;
        $port = $port === null ? '-' : trim((string) $port);
        $message = trim((string) ($event['message'] ?? '-')) ?: '-';
        $description = trim((string) (
            $event['description']
            ?? data_get($event, 'data.description')
            ?? data_get($event, 'data.ifAlias')
            ?? ''
        ));
        if ($description === '') {
            $description = 'empty';
        }

        $eventId = $event['event_id']
            ?? $event['id']
            ?? data_get($event, 'data.event_id')
            ?? data_get($event, 'data.id')
            ?? null;
        $eventId = is_scalar($eventId) ? trim((string) $eventId) : '';
        if ($eventId === '') {
            $eventId = '-';
        }

        return [
            'type' => $type,
            'raw_type' => $rawType !== '' ? $rawType : $type,
            'severity' => $severity,
            'device_id' => $deviceId,
            'device_name' => $deviceName !== '' ? $deviceName : '-',
            'device_ip' => $deviceIp !== '' ? $deviceIp : '-',
            'port' => $port !== '' ? $port : '-',
            'message' => $message,
            'timestamp' => $timestamp,
            'description' => $description,
            'event_id' => $eventId,
            'data' => $event['data'] ?? [],
        ];
    }

    private function normalizeEventTimestamp(mixed $value): string
    {
        $parsed = $this->parseEventTimestamp($value);
        if ($parsed !== null) {
            return $parsed->format('Y-m-d H:i:s');
        }

        $fallback = trim((string) $value);
        if ($fallback !== '') {
            return $fallback;
        }

        return now($this->resolveSystemTimezone())->format('Y-m-d H:i:s');
    }

    private function shouldNotify(User $user, array $event): bool
    {
        $chatIds = $this->extractChatIds($user);
        if (!($user->telegram_enabled ?? false) || empty($chatIds)) {
            return false;
        }

        $selectedDevices = $this->normalizeDeviceIds($user->telegram_devices ?? []);
        if (empty($selectedDevices)) {
            $selectedDevices = $this->resolveFallbackScopedDeviceIds($user);
        }

        if (!empty($selectedDevices)) {
            if (!$event['device_id'] || !in_array((int) $event['device_id'], $selectedDevices, true)) {
                return false;
            }
        }

        $deviceId = is_numeric($event['device_id'] ?? null) ? (int) $event['device_id'] : 0;
        $deviceInterfaceMap = $this->normalizeDeviceInterfaceMap($user->telegram_device_interfaces ?? []);
        $hasDeviceSpecificInterfaces = $deviceId > 0 && isset($deviceInterfaceMap[$deviceId]);
        if ($hasDeviceSpecificInterfaces) {
            $allowedInterfaces = $deviceInterfaceMap[$deviceId];
            $eventPort = trim((string) ($event['port'] ?? ''));
            $requiresInterfaceFilter = $eventPort !== ''
                && $eventPort !== '-'
                && strcasecmp($eventPort, 'n/a') !== 0;

            if (
                $requiresInterfaceFilter
                && !empty($allowedInterfaces)
                && !$this->portMatchesExpression($eventPort, implode(',', $allowedInterfaces))
            ) {
                return false;
            }
        }

        $severities = $this->normalizeSimpleList($user->telegram_severities ?? self::DEFAULT_SEVERITIES);
        if (empty($severities)) {
            $severities = self::DEFAULT_SEVERITIES;
        }

        if (!in_array($event['severity'], $severities, true)) {
            return false;
        }

        $eventTypes = $this->normalizeSimpleList($user->telegram_event_types ?? self::DEFAULT_EVENT_TYPES);
        if (empty($eventTypes)) {
            $eventTypes = self::DEFAULT_EVENT_TYPES;
        }

        if (!$this->eventTypeMatches($event['type'], $eventTypes)) {
            return false;
        }

        return true;
    }

    private function resolveFallbackScopedDeviceIds(User $user): array
    {
        $deviceIds = $user->devices()->pluck('id')->map(fn ($id) => (int) $id)->all();

        try {
            $permittedIds = $user->permittedDevices()->pluck('devices.id')->map(fn ($id) => (int) $id)->all();
            $deviceIds = array_merge($deviceIds, $permittedIds);
        } catch (\Throwable) {
            // Ignore optional permissions table failures.
        }

        if (DeviceEventPermission::supportsScopedAccess()) {
            try {
                $eventScopedIds = $user->eventScopedDevices()->pluck('devices.id')->map(fn ($id) => (int) $id)->all();
                $deviceIds = array_merge($deviceIds, $eventScopedIds);
            } catch (\Throwable) {
                // Ignore optional event scope table failures.
            }
        }

        return $this->normalizeDeviceIds($deviceIds);
    }

    private function buildDedupeKey(User $user, array $event, string $chatId): string
    {
        $devicePart = $event['device_id'] ?? 0;
        $portPart = (string) ($event['port'] ?? '-');

        return 'tg_notify:' . $user->id . ':' . sha1(implode('|', [
            $event['type'],
            $devicePart,
            $portPart,
            $event['severity'],
            $chatId,
        ]));
    }

    private function extractChatIds(User $user): array
    {
        $raw = $user->telegram_chat_id ?? '';

        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $ids[] = $part;
        }

        return array_values(array_unique($ids));
    }

    private function renderMessage(User $user, array $event): string
    {
        $template = $this->resolveTemplateForEvent($user, $event);
        $severityLabel = $this->resolveSeverityLabel($event['severity'] ?? '');
        $severityBadge = '[' . $severityLabel . ']';
        $eventSymbol = $this->resolveEventSymbol(
            (string) ($event['type'] ?? ''),
            (string) ($event['severity'] ?? '')
        );
        $headline = $this->buildHeadline($event);
        $detectedAt = $this->formatDetectedAt($event['timestamp'] ?? '');
        if ($template === '') {
            return sprintf(
                "%s\nDescription: %s\nDetected at %s\n\nswitch_name: %s\nSeverity: %s\nSeverity: %s\nEvent ID: %s",
                $headline,
                $event['description'] ?? 'empty',
                $detectedAt,
                $event['device_name'],
                $severityLabel,
                $severityBadge,
                $event['event_id'] ?? '-'
            );
        }

        $placeholders = [
            '{deviceName}' => $event['device_name'] ?: '-',
            '{deviceIp}' => $event['device_ip'] ?: '-',
            '{port}' => $event['port'] ?: '-',
            '{severity}' => $event['severity'] ?: '-',
            '{severityLabel}' => $severityLabel,
            '{severityBadge}' => $severityBadge,
            '{severitySymbol}' => $this->resolveSeveritySymbol($event['severity'] ?? ''),
            '{eventSymbol}' => $eventSymbol,
            '{type}' => $event['type'] ?: '-',
            '{timestamp}' => $event['timestamp'] ?: '-',
            '{detectedAt}' => $detectedAt,
            '{message}' => $event['message'] ?: '-',
            '{description}' => $event['description'] ?? 'empty',
            '{switchName}' => $event['device_name'] ?: '-',
            '{eventId}' => $event['event_id'] ?? '-',
            '{headline}' => $headline,
        ];

        return strtr($template, $placeholders);
    }

    private function resolveTemplateForEvent(User $user, array $event): string
    {
        $globalConfig = $this->resolveGlobalTemplateConfig();
        if (($globalConfig['enabled'] ?? false) === true) {
            return $this->resolveTemplateBySeverity(
                (string) ($globalConfig['default'] ?? ''),
                (array) ($globalConfig['severity_templates'] ?? []),
                (string) ($event['severity'] ?? '')
            );
        }

        $legacyConfig = $this->decodeLegacyUserTemplate((string) ($user->telegram_template ?? ''));
        return $this->resolveTemplateBySeverity(
            $legacyConfig['default'],
            $legacyConfig['severity_templates'],
            (string) ($event['severity'] ?? '')
        );
    }

    private function resolveTemplateBySeverity(string $defaultTemplate, array $severityTemplates, string $severity): string
    {
        $severity = strtolower(trim($severity));
        if ($severity !== '' && isset($severityTemplates[$severity])) {
            return (string) $severityTemplates[$severity];
        }

        return trim($defaultTemplate);
    }

    /**
     * @return array{enabled:bool,default:string,severity_templates:array<string,string>}
     */
    private function resolveGlobalTemplateConfig(): array
    {
        if ($this->globalTemplateConfig !== null) {
            return $this->globalTemplateConfig;
        }

        $defaultTemplate = '';
        $severityTemplates = [];

        if (AppSetting::supportsStorage()) {
            $defaultTemplate = trim((string) AppSetting::getValue(self::TELEGRAM_TEMPLATE_DEFAULT_SETTING, ''));
            foreach (self::TELEGRAM_TEMPLATE_SEVERITY_SETTINGS as $severity => $settingKey) {
                $value = trim((string) AppSetting::getValue($settingKey, ''));
                if ($value !== '') {
                    $severityTemplates[$severity] = $value;
                }
            }
        }

        $this->globalTemplateConfig = [
            'enabled' => $defaultTemplate !== '' || !empty($severityTemplates),
            'default' => $defaultTemplate,
            'severity_templates' => $severityTemplates,
        ];

        return $this->globalTemplateConfig;
    }

    /**
     * @return array{default:string,severity_templates:array<string,string>}
     */
    private function decodeLegacyUserTemplate(string $stored): array
    {
        $stored = trim($stored);
        if ($stored === '') {
            return [
                'default' => '',
                'severity_templates' => [],
            ];
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return [
                'default' => $stored,
                'severity_templates' => [],
            ];
        }

        $defaultTemplate = trim((string) ($decoded['default'] ?? ''));
        $severityTemplatesRaw = $decoded['severity_templates'] ?? ($decoded['templates_by_severity'] ?? []);
        $severityTemplates = [];
        if (is_array($severityTemplatesRaw)) {
            foreach ($severityTemplatesRaw as $severity => $template) {
                $severityKey = strtolower(trim((string) $severity));
                $templateValue = trim((string) $template);
                if ($severityKey !== '' && $templateValue !== '') {
                    $severityTemplates[$severityKey] = $templateValue;
                }
            }
        }

        return [
            'default' => $defaultTemplate,
            'severity_templates' => $severityTemplates,
        ];
    }

    private function resolveSeveritySymbol(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            'critical' => "\xF0\x9F\x9A\xA8", // alert icon
            'high' => "\xF0\x9F\x94\xB4", // red circle
            'medium' => "\xE2\x9A\xA0\xEF\xB8\x8F", // warning sign
            default => "\xE2\x84\xB9\xEF\xB8\x8F", // info sign
        };
    }

    private function resolveSeverityLabel(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Warning',
            default => 'Info',
        };
    }

    private function buildHeadline(array $event): string
    {
        $eventSymbol = $this->resolveEventSymbol(
            (string) ($event['type'] ?? ''),
            (string) ($event['severity'] ?? '')
        );
        $typeLabel = $this->humanizeType((string) ($event['type'] ?? 'event'));
        $port = trim((string) ($event['port'] ?? '-'));
        $message = trim((string) ($event['message'] ?? '-'));
        if ($message === '') {
            $message = '-';
        }

        if ($port !== '' && $port !== '-' && strcasecmp($port, 'n/a') !== 0) {
            return "{$eventSymbol} {$typeLabel}: Interface {$port}: {$message}";
        }

        return "{$eventSymbol} {$typeLabel}: {$message}";
    }

    private function resolveEventSymbol(string $type, string $severity): string
    {
        $type = $this->canonicalizeEventType($type);

        return match ($type) {
            'device.online', 'port.up' => "\xE2\x9C\x85", // check mark button
            'device.status_changed', 'port.status_changed', 'port.speed_changed' => "\xF0\x9F\x94\x84", // anticlockwise arrows button
            'device.offline', 'port.down', 'system.error' => "\xF0\x9F\x9A\xA8", // police cars revolving light
            default => $this->resolveSeveritySymbol($severity),
        };
    }

    private function humanizeType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if ($normalized === '') {
            return 'Event';
        }

        return match ($normalized) {
            'speed_changed', 'port.speed_changed' => 'Speed change',
            'link_down', 'port.down' => 'Link down',
            'link_up', 'port.up' => 'Link up',
            'device.offline' => 'Device offline',
            'device.online' => 'Device online',
            'device.status_changed' => 'Device status change',
            default => ucwords(str_replace(['.', '_', '-'], ' ', $normalized)),
        };
    }

    private function formatDetectedAt(string $timestamp): string
    {
        $parsed = $this->parseEventTimestamp($timestamp);
        if ($parsed === null) {
            return $timestamp !== '' ? $timestamp : '-';
        }

        return $parsed->format('H:i:s \o\n Y.m.d');
    }

    private function parseEventTimestamp(mixed $value): ?CarbonImmutable
    {
        $timezone = $this->resolveSystemTimezone();

        try {
            if ($value instanceof \DateTimeInterface) {
                return CarbonImmutable::instance(\DateTimeImmutable::createFromInterface($value))
                    ->setTimezone($timezone);
            }

            if (is_numeric($value)) {
                $timestamp = (int) $value;
                if ($timestamp > 9_999_999_999) {
                    $timestamp = (int) floor($timestamp / 1000);
                }

                return CarbonImmutable::createFromTimestampUTC($timestamp)
                    ->setTimezone($timezone);
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                return null;
            }

            $hasExplicitTimezone = (bool) preg_match('/([zZ]|[+\-]\d{2}:?\d{2})$/', $raw);
            $parsed = $hasExplicitTimezone
                ? CarbonImmutable::parse($raw)
                : CarbonImmutable::parse($raw, $timezone);

            return $parsed->setTimezone($timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveSystemTimezone(): string
    {
        $defaultTimezone = (string) config('app.timezone', 'UTC');
        if (!in_array($defaultTimezone, timezone_identifiers_list(), true)) {
            $defaultTimezone = 'UTC';
        }

        try {
            if (!AppSetting::supportsStorage()) {
                return $defaultTimezone;
            }

            $timezone = Cache::rememberForever(self::TIMEZONE_CACHE_KEY, function () use ($defaultTimezone): string {
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

    private function sendTelegramMessage(string $chatId, string $text, ?string $botTokenOverride = null, array $context = []): bool
    {
        $this->lastError = null;
        $botTokenOverride = is_string($botTokenOverride) ? trim($botTokenOverride) : '';
        $botToken = $botTokenOverride !== ''
            ? $botTokenOverride
            : (string) config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        if ($botToken === '' || $chatId === '') {
            $this->lastError = 'Missing bot token or chat ID.';
            Log::warning('Telegram notification skipped: missing bot token or chat id.', $context);
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(4)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => 'true',
                ]);

            if (!$response->ok()) {
                $payload = $response->json();
                $description = is_array($payload) ? ($payload['description'] ?? null) : null;
                $description = is_string($description) && $description !== '' ? $description : 'Telegram API request failed.';
                $this->lastError = "Telegram API error ({$response->status()}): {$description}";
                Log::warning('Telegram send failed.', array_merge($context, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]));
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Telegram send exception.', array_merge($context, [
                'error' => $e->getMessage(),
            ]));

            return false;
        }
    }

    private function normalizeDeviceIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            static fn ($id) => (int) $id,
            array_filter($ids, static fn ($id) => is_numeric($id) && (int) $id > 0)
        )));
    }

    private function normalizeDeviceInterfaceMap(array|string|null $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $deviceIdRaw => $interfacesRaw) {
            if (!is_numeric($deviceIdRaw) || !is_array($interfacesRaw)) {
                continue;
            }

            $deviceId = (int) $deviceIdRaw;
            if ($deviceId <= 0) {
                continue;
            }

            $interfaces = array_values(array_unique(array_map(
                static fn ($item): string => trim((string) $item),
                array_filter($interfacesRaw, static fn ($item): bool => trim((string) $item) !== '')
            )));

            if (!empty($interfaces)) {
                $normalized[$deviceId] = $interfaces;
            }
        }

        return $normalized;
    }

    private function normalizeSimpleList(array|string|null $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/\s*,\s*/', $values) ?: [];
        }

        if (!is_array($values)) {
            return [];
        }

        $items = [];
        foreach ($values as $value) {
            $normalized = strtolower(trim((string) $value));
            if ($normalized === '') {
                continue;
            }
            $items[] = $normalized;
        }

        return array_values(array_unique($items));
    }

    private function eventTypeMatches(string $eventType, array $patterns): bool
    {
        $eventType = $this->canonicalizeEventType($eventType);
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }

            $pattern = $this->canonicalizeEventType($pattern);

            if ($pattern === '*' || $pattern === $eventType) {
                return true;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($eventType, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function canonicalizeEventType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === '' || $type === '*') {
            return $type;
        }

        $isWildcard = str_ends_with($type, '.*');
        $baseType = $isWildcard ? substr($type, 0, -2) : $type;

        $canonical = match ($baseType) {
            'link_up' => 'port.up',
            'link_down' => 'port.down',
            'speed_changed' => 'port.speed_changed',
            'device_up' => 'device.online',
            'device_down' => 'device.offline',
            default => $baseType,
        };

        return $isWildcard ? $canonical . '.*' : $canonical;
    }

    private function portMatchesExpression(string $portValue, string $expression): bool
    {
        $tokens = preg_split('/\s*,\s*/', trim($expression)) ?: [];
        if (empty($tokens)) {
            return true;
        }

        $portValue = trim($portValue);
        if ($portValue === '' || $portValue === '-') {
            return false;
        }

        $numericPort = is_numeric($portValue) ? (int) $portValue : null;

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d+)-(\d+)$/', $token, $m)) {
                if ($numericPort === null) {
                    continue;
                }

                $start = (int) $m[1];
                $end = (int) $m[2];
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                if ($numericPort >= $start && $numericPort <= $end) {
                    return true;
                }

                continue;
            }

            if (preg_match('/^\d+$/', $token)) {
                if ($numericPort !== null && $numericPort === (int) $token) {
                    return true;
                }

                continue;
            }

            if (strcasecmp($token, $portValue) === 0) {
                return true;
            }
        }

        return false;
    }
}
