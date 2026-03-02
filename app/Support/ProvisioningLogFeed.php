<?php

namespace App\Support;

class ProvisioningLogFeed
{
    public static function readLines(string $path, int $limit = 200): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $limit = max(1, min($limit, 2000));
        $maxBytes = 200000;
        $size = filesize($path);
        $offset = max(0, $size - $maxBytes);
        $data = file_get_contents($path, false, null, $offset);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $data)) ?: [];

        if ($offset > 0 && count($lines) > 1) {
            array_shift($lines);
        }

        $lines = array_values(array_filter(array_map(static function ($line): string {
            return self::sanitizeUtf8(trim((string) $line));
        }, $lines), static fn ($line): bool => $line !== ''));

        return array_slice($lines, -$limit);
    }

    public static function readEvents(string $path, int $limit = 80): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $maxBytes = 500000;
        $size = filesize($path);
        $offset = max(0, $size - $maxBytes);
        $data = file_get_contents($path, false, null, $offset);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $data)) ?: [];

        if ($offset > 0 && count($lines) > 1) {
            array_shift($lines);
        }

        $events = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                continue;
            }

            if (!is_array($decoded)) {
                continue;
            }

            $events[] = self::normalizeEvent($decoded);
        }

        return array_slice($events, -$limit);
    }

    public static function summarize(array $lines, array $events = []): array
    {
        if ($events !== []) {
            $event = $events[array_key_last($events)];

            return [
                'state' => self::mapProgressState((string) ($event['state'] ?? 'info')),
                'title' => (($event['trace'] ?? 'Provisioning') ?: 'Provisioning') . ' Activity',
                'step' => $event['message']
                    ?? data_get($event, 'response.summary')
                    ?? data_get($event, 'request.summary')
                    ?? 'Waiting for provisioning activity.',
                'device' => $event['device_name'] ?? $event['device_ip'] ?? null,
                'script' => $event['script_name'] ?? data_get($event, 'request.operation'),
                'updated_at' => $event['timestamp'] ?? null,
                'trace' => $event['trace'] ?? null,
                'protocol' => $event['protocol'] ?? null,
                'layer' => $event['layer'] ?? null,
                'reason' => $event['reason'] ?? null,
                'raw_line' => $event['message'] ?? null,
            ];
        }

        return self::summarizeLines($lines);
    }

    private static function summarizeLines(array $lines): array
    {
        $summary = [
            'state' => 'idle',
            'title' => 'No active script execution',
            'step' => 'Waiting for provisioning activity.',
            'device' => null,
            'script' => null,
            'updated_at' => null,
            'trace' => null,
            'protocol' => null,
            'layer' => null,
            'reason' => null,
            'raw_line' => null,
        ];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = (string) ($lines[$index] ?? '');
            if ($line === '' || !self::isTraceLine($line)) {
                continue;
            }

            $summary['raw_line'] = $line;
            $summary['updated_at'] = self::extractTimestamp($line);
            $summary['device'] = self::findNearestContextValue($lines, $index, 'device_name');
            $summary['script'] = self::findNearestContextValue($lines, $index, 'script_name');
            $summary['trace'] = self::resolveTraceName($line);
            $summary['title'] = ($summary['trace'] ?? 'Provisioning') . ' Execution';
            $summary['state'] = self::resolveLineState($line);
            $summary['step'] = self::cleanLine($line);
            $summary['reason'] = str_contains(strtolower($line), 'failed') ? self::cleanLine($line) : null;

            return $summary;
        }

        return $summary;
    }

    private static function normalizeEvent(array $event): array
    {
        return array_filter([
            'id' => $event['id'] ?? null,
            'timestamp' => $event['timestamp'] ?? null,
            'message' => $event['message'] ?? null,
            'state' => $event['state'] ?? 'info',
            'trace' => $event['trace'] ?? null,
            'layer' => $event['layer'] ?? null,
            'protocol' => $event['protocol'] ?? null,
            'device_id' => $event['device_id'] ?? null,
            'device_name' => $event['device_name'] ?? null,
            'device_ip' => $event['device_ip'] ?? null,
            'device_hostname' => $event['device_hostname'] ?? null,
            'script_name' => $event['script_name'] ?? null,
            'request' => is_array($event['request'] ?? null) ? $event['request'] : [],
            'response' => is_array($event['response'] ?? null) ? $event['response'] : [],
            'latency_ms' => isset($event['latency_ms']) ? (int) $event['latency_ms'] : null,
            'reason' => $event['reason'] ?? null,
            'retry' => is_array($event['retry'] ?? null) ? $event['retry'] : [],
            'metrics' => is_array($event['metrics'] ?? null) ? $event['metrics'] : [],
            'failure_hints' => array_values(array_map('strval', $event['failure_hints'] ?? [])),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private static function sanitizeUtf8(string $line): string
    {
        if ($line === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $line);
        if (is_string($sanitized) && $sanitized !== '') {
            return $sanitized;
        }

        if (function_exists('mb_convert_encoding')) {
            $sanitized = @mb_convert_encoding($line, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, ASCII');
            if (is_string($sanitized)) {
                return $sanitized;
            }
        }

        return '';
    }

    private static function mapProgressState(string $state): string
    {
        return match (strtolower(trim($state))) {
            'success' => 'completed',
            'warning' => 'running',
            'failure' => 'failed',
            'running' => 'running',
            default => 'idle',
        };
    }

    private static function isTraceLine(string $line): bool
    {
        return str_contains($line, ' trace')
            || str_contains($line, '[backup-step]')
            || str_contains($line, 'provisioning log ');
    }

    private static function extractTimestamp(string $line): ?string
    {
        if (preg_match('/^\[([^\]]+)\]/', $line, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function resolveTraceName(string $line): ?string
    {
        $labels = [
            'backup trace' => 'Backup',
            'command trace' => 'Command',
            'status poll trace' => 'Status Poll',
            'events trace' => 'Events',
            'poller trace' => 'Poller',
            'support trace' => 'Support',
            'process trace' => 'Process',
            'device probe trace' => 'Device Probe',
            'device snapshot trace' => 'Device Snapshot',
        ];

        foreach ($labels as $needle => $label) {
            if (str_contains($line, $needle)) {
                return $label;
            }
        }

        if (str_contains($line, '[backup-step]')) {
            return 'Backup';
        }

        return null;
    }

    private static function resolveLineState(string $line): string
    {
        $line = strtolower($line);

        if (
            str_contains($line, ' failed')
            || str_contains($line, 'exception')
            || str_contains($line, 'validation failed')
            || str_contains($line, 'script missing')
            || str_contains($line, 'verification failed')
        ) {
            return 'failed';
        }

        if (
            str_contains($line, ' completed')
            || str_contains($line, 'verification succeeded')
            || str_contains($line, 'disabled')
        ) {
            return 'completed';
        }

        if (
            str_contains($line, ' launching')
            || str_contains($line, ' started')
            || str_contains($line, 'request received')
            || str_contains($line, ' stdout:')
            || str_contains($line, ' stderr:')
            || str_contains($line, '[backup-step]')
            || str_contains($line, ' active; waiting')
        ) {
            return 'running';
        }

        return 'idle';
    }

    private static function cleanLine(string $line): string
    {
        $line = preg_replace('/^\[[^\]]+\]\s+[a-z0-9_.-]+:\s*/i', '', $line) ?? $line;
        $line = preg_replace('/\s+\{".*$/', '', $line) ?? $line;

        $prefixes = [
            'backup trace stdout: ',
            'backup trace stderr: ',
            'backup trace: ',
            'command trace stdout: ',
            'command trace stderr: ',
            'command trace: ',
            'status poll trace stdout: ',
            'status poll trace stderr: ',
            'status poll trace: ',
            'device probe trace stdout: ',
            'device probe trace stderr: ',
            'device probe trace: ',
            'device snapshot trace: ',
            'events trace stdout: ',
            'events trace stderr: ',
            'events trace: ',
            'poller trace stdout: ',
            'poller trace stderr: ',
            'poller trace: ',
            'support trace: ',
            'process trace stdout: ',
            'process trace stderr: ',
            'process trace: ',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with(strtolower($line), strtolower($prefix))) {
                $line = substr($line, strlen($prefix));
                break;
            }
        }

        $line = str_replace('[backup-step] ', '', $line);

        return trim($line) !== '' ? trim($line) : 'Waiting for provisioning activity.';
    }

    private static function findNearestContextValue(array $lines, int $startIndex, string $key): ?string
    {
        $pattern = '/"' . preg_quote($key, '/') . '":"([^"]+)"/';

        for ($index = $startIndex; $index >= 0; $index--) {
            $line = (string) ($lines[$index] ?? '');
            if (preg_match($pattern, $line, $matches) === 1) {
                return stripslashes($matches[1]);
            }
        }

        return null;
    }
}
