<?php

namespace App\Console\Commands;

use App\Support\ProvisioningTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PollDeviceEvents extends Command
{
    protected $signature = 'events:poll';

    protected $description = 'Poll device/interface events and store them in the database.';
    private ?object $telegramNotifier = null;

    public function __construct()
    {
        parent::__construct();
        $this->telegramNotifier = $this->resolveTelegramNotifier();
    }

    public function handle(): int
    {
        $scriptPath = base_path('scripts/poller.php');
        $beforeInterfaceEventId = $this->latestTableId('interface_events');
        $beforeDeviceEventId = $this->latestTableId('device_events');

        ProvisioningTrace::log('events poll trace: command started', [
            'trace' => 'events polling',
            'trigger' => 'events:poll',
            'script_name' => 'poller.php',
            'script_path' => $scriptPath,
        ]);

        if (!is_file($scriptPath)) {
            $message = 'poller.php not found in scripts folder.';
            $this->error($message);
            Log::warning('events:poll failed: script missing', [
                'path' => $scriptPath,
            ]);
            ProvisioningTrace::log('events poll trace: script missing', [
                'trace' => 'events polling',
                'trigger' => 'events:poll',
                'script_name' => 'poller.php',
                'script_path' => $scriptPath,
            ]);
            return self::FAILURE;
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $command = [$phpBinary, $scriptPath];
        $process = new Process($command, base_path());
        $process->setTimeout(180);
        $process->setEnv(array_merge($_ENV, $_SERVER, ProvisioningTrace::childProcessEnv()));
        $result = ProvisioningTrace::runProcess($process, [
            'label' => 'events poll trace',
            'line_prefix' => 'events poll trace',
            'context' => [
                'trace' => 'events polling',
                'layer' => 'data_polling',
                'protocol' => 'SNMP',
                'trigger' => 'events:poll',
                'script_name' => 'poller.php',
                'script_path' => $scriptPath,
                'command' => $command,
            ],
        ]);

        if (!$result['ok']) {
            $output = $result['output'];
            Log::warning('events:poll execution failed', [
                'exit_code' => $result['exit_code'],
                'output' => $output,
            ]);
            $this->error('events:poll execution failed.');
            if ($output !== '') {
                $this->line($output);
            }
            return self::FAILURE;
        }

        $output = $result['output'];
        if ($output !== '') {
            $this->line($output);
        }

        $this->dispatchTelegramNotificationsForNewEvents($beforeInterfaceEventId, $beforeDeviceEventId);
        $this->info('events:poll completed.');
        return self::SUCCESS;
    }

    private function latestTableId(string $table): int
    {
        try {
            return (int) (DB::table($table)->max('id') ?? 0);
        } catch (\Throwable) {
            return 0;
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
            Log::warning('events:poll telegram notifier unavailable', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function dispatchTelegramNotificationsForNewEvents(int $beforeInterfaceEventId, int $beforeDeviceEventId): void
    {
        $notifier = $this->telegramNotifier;
        if ($notifier === null || !method_exists($notifier, 'notifyUsersForEvent')) {
            return;
        }

        try {
            $interfaceEvents = DB::table('interface_events')
                ->where('id', '>', max(0, $beforeInterfaceEventId))
                ->orderBy('id')
                ->get([
                    'id',
                    'device_id',
                    'device_name',
                    'ifName',
                    'ifDescr',
                    'ifAlias',
                    'event_type',
                    'severity',
                    'old_status',
                    'new_status',
                    'old_speed_mbps',
                    'new_speed_mbps',
                    'opened_at',
                ]);
        } catch (\Throwable $e) {
            Log::warning('events:poll telegram dispatch skipped (interface_events query failed)', [
                'error' => $e->getMessage(),
            ]);
            $interfaceEvents = collect();
        }

        foreach ($interfaceEvents as $row) {
            $payload = $this->buildInterfaceEventPayload($row);
            if (($payload['type'] ?? '') === '') {
                continue;
            }

            try {
                $notifier->notifyUsersForEvent($payload);
            } catch (\Throwable $e) {
                Log::warning('events:poll telegram dispatch failed for interface event', [
                    'event_id' => $payload['event_id'] ?? null,
                    'device_id' => $payload['device_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $deviceEvents = DB::table('device_events')
                ->where('id', '>', max(0, $beforeDeviceEventId))
                ->orderBy('id')
                ->get([
                    'id',
                    'device_id',
                    'device_name',
                    'ip',
                    'event_type',
                    'severity',
                    'opened_at',
                ]);
        } catch (\Throwable $e) {
            Log::warning('events:poll telegram dispatch skipped (device_events query failed)', [
                'error' => $e->getMessage(),
            ]);
            $deviceEvents = collect();
        }

        foreach ($deviceEvents as $row) {
            $payload = $this->buildDeviceEventPayload($row);
            if (($payload['type'] ?? '') === '') {
                continue;
            }

            try {
                $notifier->notifyUsersForEvent($payload);
            } catch (\Throwable $e) {
                Log::warning('events:poll telegram dispatch failed for device event', [
                    'event_id' => $payload['event_id'] ?? null,
                    'device_id' => $payload['device_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildInterfaceEventPayload(object $row): array
    {
        $rawType = strtolower(trim((string) ($row->event_type ?? '')));
        if ($rawType === '') {
            return [];
        }

        $oldSpeed = is_numeric($row->old_speed_mbps ?? null) ? (int) $row->old_speed_mbps : null;
        $newSpeed = is_numeric($row->new_speed_mbps ?? null) ? (int) $row->new_speed_mbps : null;
        $port = trim((string) ($row->ifName ?? ''));
        if ($port === '') {
            $port = trim((string) ($row->ifDescr ?? ''));
        }
        if ($port === '') {
            $port = '-';
        }

        $message = match ($rawType) {
            'speed_changed', 'port.speed_changed' => ($oldSpeed !== null && $newSpeed !== null)
                ? "Speed changed {$oldSpeed} -> {$newSpeed} Mbps"
                : 'Speed changed',
            'link_down', 'port.down' => 'Link is down',
            'link_up', 'port.up' => 'Link is up',
            default => 'Interface event detected',
        };

        return [
            'type' => $rawType,
            'severity' => $this->resolveSeverityFromInterfaceEvent($rawType, (string) ($row->severity ?? '')),
            'device_id' => is_numeric($row->device_id ?? null) ? (int) $row->device_id : null,
            'device_name' => trim((string) ($row->device_name ?? '')) ?: '-',
            'device_ip' => '-',
            'port' => $port,
            'message' => $message,
            'description' => trim((string) ($row->ifAlias ?? '')) ?: 'empty',
            'timestamp' => $this->toDateTimeString($row->opened_at ?? null),
            'event_id' => (string) ($row->id ?? '-'),
            'data' => [
                'event_type' => $rawType,
                'old_status' => is_numeric($row->old_status ?? null) ? (int) $row->old_status : null,
                'new_status' => is_numeric($row->new_status ?? null) ? (int) $row->new_status : null,
                'old_speed_mbps' => $oldSpeed,
                'new_speed_mbps' => $newSpeed,
            ],
        ];
    }

    private function buildDeviceEventPayload(object $row): array
    {
        $rawType = strtolower(trim((string) ($row->event_type ?? '')));
        if ($rawType === '') {
            return [];
        }

        $message = match ($rawType) {
            'device.offline', 'device_down', 'device_offline' => 'Device is offline',
            'device.online', 'device_up', 'device_online' => 'Device is online',
            default => 'Device event detected',
        };

        return [
            'type' => $rawType,
            'severity' => $this->resolveSeverityFromDeviceEvent($rawType, (string) ($row->severity ?? '')),
            'device_id' => is_numeric($row->device_id ?? null) ? (int) $row->device_id : null,
            'device_name' => trim((string) ($row->device_name ?? '')) ?: '-',
            'device_ip' => trim((string) ($row->ip ?? '')) ?: '-',
            'port' => '-',
            'message' => $message,
            'description' => 'empty',
            'timestamp' => $this->toDateTimeString($row->opened_at ?? null),
            'event_id' => (string) ($row->id ?? '-'),
            'data' => [
                'event_type' => $rawType,
            ],
        ];
    }

    private function resolveSeverityFromInterfaceEvent(string $type, string $severity): string
    {
        $type = strtolower(trim($type));
        $normalizedSeverity = strtolower(trim($severity));
        $normalizedSeverity = match ($normalizedSeverity) {
            'critical', 'crit' => 'critical',
            'high' => 'high',
            'average', 'medium', 'warn', 'warning' => 'medium',
            'info', 'low' => 'low',
            default => '',
        };

        if ($normalizedSeverity !== '') {
            // Keep source severity when it is explicit, but enforce minimum alert
            // levels for key interface transitions so user default filters don't
            // silently drop important events.
            if (in_array($type, ['link_down', 'port.down'], true)) {
                return in_array($normalizedSeverity, ['critical', 'high'], true) ? $normalizedSeverity : 'high';
            }

            if (in_array($type, ['speed_changed', 'port.speed_changed', 'port.status_changed'], true)) {
                return in_array($normalizedSeverity, ['critical', 'high', 'medium'], true) ? $normalizedSeverity : 'medium';
            }

            if (in_array($type, ['link_up', 'port.up'], true)) {
                return in_array($normalizedSeverity, ['critical', 'high', 'medium', 'low'], true) ? $normalizedSeverity : 'low';
            }

            return $normalizedSeverity;
        }

        return match ($type) {
            'link_down', 'port.down' => 'high',
            'speed_changed', 'port.speed_changed' => 'medium',
            default => 'low',
        };
    }

    private function resolveSeverityFromDeviceEvent(string $type, string $severity): string
    {
        $type = strtolower(trim($type));
        $normalizedSeverity = strtolower(trim($severity));
        $normalizedSeverity = match ($normalizedSeverity) {
            'critical', 'crit' => 'critical',
            'high' => 'high',
            'average', 'medium', 'warn', 'warning' => 'medium',
            'info', 'low' => 'low',
            default => '',
        };

        if ($normalizedSeverity !== '') {
            return $normalizedSeverity;
        }

        return match ($type) {
            'device.offline', 'device_down', 'device_offline' => 'high',
            default => 'low',
        };
    }

    private function toDateTimeString(mixed $value): string
    {
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        return now()->toDateTimeString();
    }
}
