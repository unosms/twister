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

        $lines = array_values(array_filter($lines, static fn ($line): bool => trim((string) $line) !== ''));

        return array_slice($lines, -$limit);
    }

    public static function summarize(array $lines): array
    {
        $summary = [
            'state' => 'idle',
            'title' => 'No active script execution',
            'step' => 'Waiting for provisioning activity.',
            'device' => null,
            'script' => null,
            'updated_at' => null,
            'trace' => null,
            'raw_line' => null,
        ];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = (string) ($lines[$index] ?? '');
            if ($line === '') {
                continue;
            }

            if (!self::isTraceLine($line)) {
                continue;
            }

            $summary['raw_line'] = $line;
            $summary['updated_at'] = self::extractTimestamp($line);
            $summary['device'] = self::findNearestContextValue($lines, $index, 'device_name');
            $summary['script'] = self::findNearestContextValue($lines, $index, 'script_name');
            $summary['trace'] = self::resolveTraceName($line);
            $summary['title'] = self::resolveTitle($summary['trace']);
            $summary['state'] = self::resolveState($line);
            $summary['step'] = self::cleanLine($line);

            return $summary;
        }

        return $summary;
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

    private static function resolveTitle(?string $trace): string
    {
        return $trace ? $trace . ' Execution' : 'Provisioning Execution';
    }

    private static function resolveState(string $line): string
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
