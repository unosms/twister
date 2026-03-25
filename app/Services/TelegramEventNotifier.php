<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramEventNotifier
{
    private const DEFAULT_SEVERITIES = ['high', 'critical'];
    private const DEFAULT_EVENT_TYPES = ['device.offline', 'port.down'];
    private ?string $lastError = null;

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
            'timestamp' => now()->toDateTimeString(),
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
        $type = strtolower(trim((string) ($event['type'] ?? $event['event_type'] ?? '')));
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

        $timestamp = (string) ($event['timestamp'] ?? now()->toDateTimeString());
        $port = $event['port'] ?? $event['ifName'] ?? $event['interface'] ?? null;
        $port = $port === null ? '-' : trim((string) $port);

        return [
            'type' => $type,
            'severity' => $severity,
            'device_id' => $deviceId,
            'device_name' => $deviceName !== '' ? $deviceName : '-',
            'device_ip' => $deviceIp !== '' ? $deviceIp : '-',
            'port' => $port !== '' ? $port : '-',
            'message' => trim((string) ($event['message'] ?? '-')) ?: '-',
            'timestamp' => $timestamp,
            'data' => $event['data'] ?? [],
        ];
    }

    private function shouldNotify(User $user, array $event): bool
    {
        $chatIds = $this->extractChatIds($user);
        if (!($user->telegram_enabled ?? false) || empty($chatIds)) {
            return false;
        }

        $selectedDevices = $this->normalizeDeviceIds($user->telegram_devices ?? []);
        if (empty($selectedDevices)) {
            $selectedDevices = $user->devices()->pluck('id')->map(fn ($id) => (int) $id)->all();
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
            if (!empty($allowedInterfaces) && !$this->portMatchesExpression($event['port'], implode(',', $allowedInterfaces))) {
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
        if ($template === '') {
            return sprintf(
                "[%s] %s\nDevice: %s (%s)\nPort: %s\n%s\nTime: %s",
                $event['severity'],
                $event['type'],
                $event['device_name'],
                $event['device_ip'],
                $event['port'],
                $event['message'],
                $event['timestamp']
            );
        }

        $placeholders = [
            '{deviceName}' => $event['device_name'] ?: '-',
            '{deviceIp}' => $event['device_ip'] ?: '-',
            '{port}' => $event['port'] ?: '-',
            '{severity}' => $event['severity'] ?: '-',
            '{severitySymbol}' => $this->resolveSeveritySymbol($event['severity'] ?? ''),
            '{type}' => $event['type'] ?: '-',
            '{timestamp}' => $event['timestamp'] ?: '-',
            '{message}' => $event['message'] ?: '-',
        ];

        return strtr($template, $placeholders);
    }

    private function resolveTemplateForEvent(User $user, array $event): string
    {
        $stored = trim((string) ($user->telegram_template ?? ''));
        if ($stored === '') {
            return '';
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return $stored;
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

        $severity = strtolower(trim((string) ($event['severity'] ?? '')));
        if ($severity !== '' && isset($severityTemplates[$severity])) {
            return $severityTemplates[$severity];
        }

        return $defaultTemplate;
    }

    private function resolveSeveritySymbol(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            'critical' => '🚨',
            'high' => '🔶',
            'medium' => '⚠️',
            default => 'ℹ️',
        };
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
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }

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
