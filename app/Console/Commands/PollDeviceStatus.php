<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\TelemetryLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class PollDeviceStatus extends Command
{
    protected $signature = 'devices:poll-status {--limit=} {--device=}';
    protected $description = 'Poll devices via TCP and update status, uptime, and temperature when available.';
    private const OFFLINE_FAILURE_THRESHOLD = 2;
    private const PROBE_FAILURE_TTL_SECONDS = 600;
    private ?object $telegramNotifier = null;

    public function __construct()
    {
        parent::__construct();
        $this->telegramNotifier = $this->resolveTelegramNotifier();
    }

    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?? 0);
        $deviceId = (int) ($this->option('device') ?? 0);
        $checked = 0;
        $online = 0;
        $mode = $deviceId > 0 ? 'single' : ($limit > 0 ? 'limited' : 'all');

        $this->logProvisioning('device poll cycle started', [
            'mode' => $mode,
            'limit' => $limit > 0 ? $limit : null,
            'device_id' => $deviceId > 0 ? $deviceId : null,
        ]);

        if ($deviceId > 0) {
            $device = Device::find($deviceId);
            if (!$device) {
                $this->logProvisioning('device poll requested for unknown device', [
                    'device_id' => $deviceId,
                ]);
                $this->error("Device {$deviceId} not found.");
                return 1;
            }
            $result = $this->pollDevice($device);
            $checked++;
            if ($result) {
                $online++;
            }
        } elseif ($limit > 0) {
            $devices = Device::orderBy('id')->limit($limit)->get();
            foreach ($devices as $device) {
                $result = $this->pollDevice($device);
                $checked++;
                if ($result) {
                    $online++;
                }
            }
        } else {
            Device::orderBy('id')->chunkById(50, function ($devices) use (&$checked, &$online) {
                foreach ($devices as $device) {
                    $result = $this->pollDevice($device);
                    $checked++;
                    if ($result) {
                        $online++;
                    }
                }
            });
        }

        $this->logProvisioning('device poll cycle completed', [
            'mode' => $mode,
            'checked' => $checked,
            'online' => $online,
            'offline' => max(0, $checked - $online),
        ]);

        $this->info("Checked {$checked} devices. Online: {$online}.");
        return 0;
    }

    private function pollDevice(Device $device): bool
    {
        $previousStatus = strtolower(trim((string) ($device->status ?? 'offline')));
        if ($previousStatus === '') {
            $previousStatus = 'offline';
        }

        $this->logProvisioning('polling device', [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'previous_status' => $previousStatus,
        ]);
        $this->recordTelemetrySnapshot($device, $previousStatus);

        $ip = $this->resolveDeviceIp($device);
        if (!$ip) {
            Cache::forget('device_probe_failures:' . $device->id);
            $device->update(['status' => 'offline']);
            $this->logProvisioning('device marked offline: missing ip', [
                'device_id' => $device->id,
                'device_name' => $device->name,
            ]);
            $this->notifyStatusChange($device, $previousStatus, 'offline', null);
            return false;
        }

        $meta = $this->normalizeMetadata($device->metadata);
        if ($this->isMonitoringDisabled($meta)) {
            Cache::forget('device_probe_failures:' . $device->id);
            $device->update(['status' => 'offline']);
            $this->logProvisioning('device marked offline: monitoring disabled', [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'device_ip' => $ip,
            ]);
            $this->notifyStatusChange($device, $previousStatus, 'offline', $ip);
            return false;
        }

        $ports = $this->resolveProbePorts($device);
        $probeMethod = 'tcp';
        $probeOnline = $this->tcpProbe($ip, $ports);
        if (!$probeOnline) {
            $probeMethod = 'ping';
            $probeOnline = $this->pingHost($ip);
            if (!$probeOnline) {
                $probeMethod = 'none';
            }
        }

        $isOnline = $this->resolveEffectiveOnlineState($device, $probeOnline);
        $newStatus = $isOnline ? 'online' : 'offline';
        $updates = ['status' => $newStatus];
        if ($probeOnline) {
            $updates['last_seen_at'] = now();

            $meta = $this->normalizeMetadata($device->metadata);
            $snmp = $this->fetchSnmpData($device, $ip);
            if ($snmp) {
                if (array_key_exists('uptime', $snmp)) {
                    $meta['uptime'] = $snmp['uptime'];
                }
                if (array_key_exists('temperature', $snmp)) {
                    $meta['temperature'] = $snmp['temperature'];
                }
            }

            $temp = $this->fetchScriptTemperature($device, $ip);
            if ($temp !== null) {
                $meta['temperature'] = $temp;
            }

            $uptime = $this->fetchScriptUptime($device, $ip);
            if ($uptime !== null) {
                $meta['uptime'] = $uptime;
            }

            if (!empty($meta)) {
                $updates['metadata'] = $meta;
            }
        }

        $device->update($updates);
        $this->logProvisioning('device poll result', [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_ip' => $ip,
            'ports' => $ports,
            'probe_online' => $probeOnline,
            'probe_method' => $probeMethod,
            'status_before' => $previousStatus,
            'status_after' => $newStatus,
            'status_changed' => $previousStatus !== $newStatus,
            'last_seen_updated' => array_key_exists('last_seen_at', $updates),
            'snmp_uptime_present' => !empty(data_get($updates, 'metadata.uptime')),
            'temperature_present' => !empty(data_get($updates, 'metadata.temperature')),
        ]);
        $this->notifyStatusChange($device, $previousStatus, $newStatus, $ip);
        return $isOnline;
    }


    private function resolveEffectiveOnlineState(Device $device, bool $probeOnline): bool
    {
        $failureKey = 'device_probe_failures:' . $device->id;
        $wasOnline = strtolower((string) ($device->status ?? 'offline')) === 'online';

        if ($probeOnline) {
            Cache::forget($failureKey);
            return true;
        }

        $failures = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, now()->addSeconds(self::PROBE_FAILURE_TTL_SECONDS));

        if ($wasOnline && $failures < self::OFFLINE_FAILURE_THRESHOLD) {
            return true;
        }

        return false;
    }
    private function resolveDeviceIp(Device $device): ?string
    {
        $meta = $this->normalizeMetadata($device->metadata);
        $ciscoIp = data_get($meta, 'cisco.ip_address');
        $mimosaIp = data_get($meta, 'mimosa.ip');
        $serverIp = data_get($meta, 'server.ip_address');
        $mikrotikIp = data_get($meta, 'mikrotik.ip_address');
        // Prefer the canonical device column first to avoid stale metadata IPs.
        $ip = $device->ip_address ?: ($ciscoIp ?: ($mimosaIp ?: ($serverIp ?: $mikrotikIp)));
        $ip = is_string($ip) ? trim($ip) : $ip;
        return $ip !== '' ? $ip : null;
    }

    private function resolveProbePorts(Device $device): array
    {
        $meta = $this->normalizeMetadata($device->metadata);
        $snmpPort = data_get($meta, 'snmp_port');
        if (!is_numeric($snmpPort)) {
            $snmpPort = data_get($meta, 'server.snmp_port');
        }
        if (!is_numeric($snmpPort)) {
            $snmpPort = data_get($meta, 'mikrotik.snmp_port');
        }
        $snmpPort = is_numeric($snmpPort) ? (int) $snmpPort : null;
        $sshPort = data_get($meta, 'server.ssh_port');
        $sshPort = is_numeric($sshPort) ? (int) $sshPort : null;
        $mikrotikPorts = [
            data_get($meta, 'mikrotik.winbox_port'),
            data_get($meta, 'mikrotik.ssh_port'),
            data_get($meta, 'mikrotik.telnet_port'),
            data_get($meta, 'mikrotik.api_port'),
            data_get($meta, 'mikrotik.api_ssl_port'),
            data_get($meta, 'mikrotik.ftp_port'),
            data_get($meta, 'mikrotik.http_port'),
            data_get($meta, 'mikrotik.snmp_port'),
        ];
        $mikrotikPorts = array_values(array_filter(array_map(
            static fn ($port) => is_numeric($port) ? (int) $port : null,
            $mikrotikPorts
        )));

        $defaults = [22, 23, 161];

        return array_values(array_filter(array_merge([$sshPort, $snmpPort], $mikrotikPorts, $defaults)));
    }

    private function tcpProbe(string $ip, array $ports): bool
    {
        $ports = array_values(array_unique(array_filter($ports, static fn ($port) => is_int($port) && $port > 0)));
        foreach ($ports as $port) {
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($ip, $port, $errno, $errstr, 0.8);
            if ($socket) {
                fclose($socket);
                return true;
            }
        }
        return false;
    }

    private function pingHost(string $ip): bool
    {
        $timeoutMs = 1000;
        if (PHP_OS_FAMILY === 'Windows') {
            $command = ['ping', '-n', '1', '-w', (string) $timeoutMs, $ip];
        } else {
            $timeoutSec = max(1, (int) ceil($timeoutMs / 1000));
            $command = ['ping', '-c', '1', '-W', (string) $timeoutSec, $ip];
        }

        $process = new Process($command);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    private function fetchSnmpData(Device $device, string $ip): array
    {
        if (!function_exists('snmp2_get') && !function_exists('snmpget')) {
            return [];
        }

        $meta = $this->normalizeMetadata($device->metadata);
        $community = data_get($meta, 'snmp_community') ?: 'public';
        $version = strtolower((string) data_get($meta, 'snmp_version', '2c'));
        $snmpPort = data_get($meta, 'snmp_port');
        $host = $ip;
        if (is_numeric($snmpPort)) {
            $host = $ip . ':' . (int) $snmpPort;
        }

        $timeout = 500000;
        $retries = 1;

        $uptimeOid = '1.3.6.1.2.1.1.3.0';
        $uptimeRaw = $this->snmpGet($version, $host, $community, $uptimeOid, $timeout, $retries);
        $uptime = $this->parseSnmpValue($uptimeRaw);

        $temperature = null;
        $type = strtoupper((string) ($device->type ?? ''));
        if ($type === 'CISCO') {
            $tempOids = [
                '1.3.6.1.4.1.9.2.1.58.0',
                '1.3.6.1.4.1.9.9.13.1.3.1.3.1',
            ];
            foreach ($tempOids as $oid) {
                $tempRaw = $this->snmpGet($version, $host, $community, $oid, $timeout, $retries);
                $tempParsed = $this->parseSnmpValue($tempRaw, true);
                if ($tempParsed !== null) {
                    $temperature = $tempParsed;
                    break;
                }
            }
        }

        $payload = [];
        if ($uptime !== null) {
            $payload['uptime'] = $uptime;
        }
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }
        return $payload;
    }

    private function snmpGet(string $version, string $host, string $community, string $oid, int $timeout, int $retries): ?string
    {
        try {
            if ($version === '1' && function_exists('snmpget')) {
                $value = @snmpget($host, $community, $oid, $timeout, $retries);
                return $value !== false ? (string) $value : null;
            }
            if (function_exists('snmp2_get')) {
                $value = @snmp2_get($host, $community, $oid, $timeout, $retries);
                return $value !== false ? (string) $value : null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function fetchScriptUptime(Device $device, string $ip): ?string
    {
        $type = strtoupper((string) ($device->type ?? ''));
        if ($type !== 'CISCO') {
            return null;
        }

        $meta = $this->normalizeMetadata($device->metadata);
        $cisco = data_get($meta, 'cisco', []);
        $model = strtoupper((string) data_get($cisco, 'switch_model', ''));
        $subtype = strtoupper((string) ($device->subtype ?? data_get($meta, 'subtype', '')));
        $isNexus = str_contains($model, 'NEXUS') || str_contains($subtype, 'NEXUS');
        $modelKnown = $model !== '' || $subtype !== '';

        $password = $this->decryptValue(data_get($cisco, 'password'));
        $enable = $this->decryptValue(data_get($cisco, 'enable_password'));
        $username = data_get($cisco, 'username') ?? data_get($cisco, 'user');

        $preferNexus = !$modelKnown && $username && !$enable;
        if ($modelKnown) {
            $candidates = [$isNexus ? 'showuptime_nexus.sh' : 'showuptime.sh'];
        } elseif ($preferNexus) {
            $candidates = ['showuptime_nexus.sh', 'showuptime.sh'];
        } else {
            $candidates = ['showuptime.sh', 'showuptime_nexus.sh'];
        }

        $env = array_filter([
            'SWITCH_IP' => $ip,
            'SWITCH_PASS' => $password,
            'SWITCH_ENA' => $enable,
            'SWITCH_USER' => $username,
        ], static fn ($value) => $value !== null && $value !== '');

        foreach ($candidates as $script) {
            $scriptPath = base_path('scripts/' . $script);
            if (!is_file($scriptPath)) {
                continue;
            }
            $timeout = $script === 'showuptime_nexus.sh' ? 90 : 40;
            $process = new Process(['bash', $scriptPath], base_path());
            $process->setTimeout($timeout);
            $process->setEnv(array_merge($_ENV, $_SERVER, $env));
            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                continue;
            } catch (\Throwable $e) {
                continue;
            }
            if (!$process->isSuccessful()) {
                continue;
            }

            $output = $this->sanitizeUtf8(trim($process->getOutput()));
            if ($output === '' || $output === 'N/A') {
                continue;
            }

            return $output;
        }

        return null;
    }

    private function fetchScriptTemperature(Device $device, string $ip): ?string
    {
        $type = strtoupper((string) ($device->type ?? ''));
        if ($type !== 'CISCO') {
            return null;
        }

        $meta = $this->normalizeMetadata($device->metadata);
        $cisco = data_get($meta, 'cisco', []);
        $model = strtoupper((string) data_get($cisco, 'switch_model', ''));
        $subtype = strtoupper((string) ($device->subtype ?? data_get($meta, 'subtype', '')));
        $isNexus = str_contains($model, 'NEXUS') || str_contains($subtype, 'NEXUS');
        $is3560 = str_contains($model, '3560') || str_contains($subtype, '3560');
        $modelKnown = $model !== '' || $subtype !== '';

        $password = $this->decryptValue(data_get($cisco, 'password'));
        $enable = $this->decryptValue(data_get($cisco, 'enable_password'));
        $username = data_get($cisco, 'username') ?? data_get($cisco, 'user');
        $name = data_get($cisco, 'name') ?? $device->name;

        $preferNexus = !$modelKnown && $username && !$enable;
        if ($modelKnown) {
            if ($isNexus) {
                $candidates = ['showtemp_nexus.sh'];
            } elseif ($is3560) {
                $candidates = ['showtemp_3560.sh'];
            } else {
                $candidates = ['showtemp.sh'];
            }
        } elseif ($preferNexus) {
            $candidates = ['showtemp_nexus.sh', 'showtemp_3560.sh', 'showtemp.sh'];
        } else {
            $candidates = ['showtemp.sh', 'showtemp_3560.sh', 'showtemp_nexus.sh'];
        }

        $env = array_filter([
            'SWITCH_IP' => $ip,
            'SWITCH_PASS' => $password,
            'SWITCH_ENA' => $enable,
            'SWITCH_USER' => $username,
            'SWITCH_NAME' => $name,
        ], static fn ($value) => $value !== null && $value !== '');

        foreach ($candidates as $script) {
            $scriptPath = base_path('scripts/' . $script);
            if (!is_file($scriptPath)) {
                continue;
            }
            $timeout = $script === 'showtemp_nexus.sh' ? 90 : 40;
            $process = new Process(['bash', $scriptPath], base_path());
            $process->setTimeout($timeout);
            $process->setEnv(array_merge($_ENV, $_SERVER, $env));
            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                continue;
            } catch (\Throwable $e) {
                continue;
            }
            if (!$process->isSuccessful()) {
                continue;
            }

            $output = $this->sanitizeUtf8(trim($process->getOutput()));
            if ($output === '' || $output === 'N/A') {
                continue;
            }

            return $output;
        }

        return null;
    }

    private function normalizeMetadata($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function isMonitoringDisabled(array $meta): bool
    {
        return !empty($meta['monitoring_disabled'])
            || !empty(data_get($meta, 'cisco.monitoring_disabled'))
            || !empty(data_get($meta, 'server.monitoring_disabled'))
            || !empty(data_get($meta, 'mikrotik.monitoring_disabled'));
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
            // ignore and fall back to null
        }

        return null;
    }
    private function notifyStatusChange(Device $device, string $previousStatus, string $newStatus, ?string $ip): void
    {
        $old = strtolower(trim($previousStatus));
        $new = strtolower(trim($newStatus));
        if ($old === '') {
            $old = 'offline';
        }
        if ($new === '') {
            $new = 'offline';
        }
        if ($old === $new) {
            return;
        }

        $eventBase = [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_ip' => $ip ?: ($device->ip_address ?? '-'),
            'port' => '-',
            'timestamp' => now()->toDateTimeString(),
            'data' => [
                'old_status' => $old,
                'new_status' => $new,
            ],
        ];

        $severity = $new === 'offline' ? 'high' : 'low';
        $message = "Device status changed from {$old} to {$new}.";
        $this->recordTelemetry(
            $device,
            $new === 'offline' ? 'warning' : 'info',
            $message,
            [
                'old_status' => $old,
                'new_status' => $new,
                'device_ip' => $eventBase['device_ip'],
            ]
        );

        $notifier = $this->telegramNotifier;
        if ($notifier === null || !method_exists($notifier, 'notifyUsersForEvent')) {
            return;
        }

        try {
            $notifier->notifyUsersForEvent(array_merge($eventBase, [
                'type' => "device.{$new}",
                'severity' => $severity,
                'message' => $message,
            ]));

            $notifier->notifyUsersForEvent(array_merge($eventBase, [
                'type' => 'device.status_changed',
                'severity' => $severity,
                'message' => $message,
            ]));
        } catch (\Throwable $e) {
            $this->logProvisioning('telegram notification failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sanitizeUtf8(string $value): string
    {
        if (function_exists('mb_convert_encoding')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        } elseif (function_exists('iconv')) {
            $value = iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: $value;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
    }

    private function decryptValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function parseSnmpValue(?string $value, bool $numericOnly = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\((\d+)\)\s*(.+)/', $value, $matches)) {
            return $numericOnly ? $matches[1] : trim($matches[2]);
        }

        if (preg_match('/(-?\d+(?:\.\d+)?)/', $value, $matches)) {
            return $matches[1];
        }

        return $numericOnly ? null : trim($value);
    }

    private function provisioningLogEnabled(): bool
    {
        return (bool) cache()->get('provisioning_log_enabled', false);
    }

    private function logProvisioning(string $message, array $context = []): void
    {
        if (!$this->provisioningLogEnabled()) {
            return;
        }

        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/provisioning.log'),
                'level' => 'debug',
            ])->debug($message, $context);
        } catch (\Throwable $e) {
            // ignore logging failures
        }
    }

    private function recordTelemetrySnapshot(Device $device, string $status): void
    {
        $key = 'telemetry_snapshot_log:' . $device->id;
        if (!Cache::add($key, true, now()->addMinutes(10))) {
            return;
        }

        $this->recordTelemetry(
            $device,
            'debug',
            'Periodic status snapshot recorded.',
            [
                'status' => $status,
                'source' => 'devices:poll-status',
            ]
        );
    }

    private function recordTelemetry(Device $device, string $level, string $message, array $payload = []): void
    {
        try {
            TelemetryLog::create([
                'device_id' => $device->id,
                'level' => strtolower(trim($level)) !== '' ? strtolower(trim($level)) : 'info',
                'message' => $message,
                'payload' => $payload,
                'recorded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // keep polling resilient if DB writes fail
        }
    }
}
















