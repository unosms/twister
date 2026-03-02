<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\TelemetryLog;
use App\Support\ProvisioningTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

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
            ProvisioningTrace::communication('status poll trace: missing device IP', $this->pollTraceContext($device, [
                'layer' => 'network_discovery',
                'protocol' => 'INTERNAL',
                'state' => 'failure',
                'reason' => 'No device IP or hostname is configured.',
                'response' => [
                    'status' => 'skipped',
                    'summary' => 'Status poll stopped before network discovery because no target address was resolved.',
                ],
            ]));
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
            ProvisioningTrace::communication('status poll trace: monitoring disabled', $this->pollTraceContext($device, [
                'layer' => 'network_discovery',
                'protocol' => 'INTERNAL',
                'state' => 'warning',
                'device_ip' => $ip,
                'reason' => 'Monitoring is disabled for this device.',
                'response' => [
                    'status' => 'skipped',
                    'summary' => 'Status poll stopped because monitoring is disabled.',
                ],
            ]));
            $this->notifyStatusChange($device, $previousStatus, 'offline', $ip);
            return false;
        }

        $ports = $this->resolveProbePorts($device);
        $probeMethod = 'tcp';
        $probeOnline = $this->tcpProbe($device, $ip, $ports);
        if (!$probeOnline) {
            $probeMethod = 'icmp';
            $probeOnline = $this->pingHost($device, $ip);
            if (!$probeOnline) {
                $probeMethod = 'none';
            }
        }

        $isOnline = $this->resolveEffectiveOnlineState($device, $probeOnline, $ip, $probeMethod);
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
        ProvisioningTrace::communication('status poll trace: device poll finalized', $this->pollTraceContext($device, [
            'layer' => 'status_transition',
            'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : ($probeMethod === 'tcp' ? 'TCP' : 'INTERNAL')),
            'state' => $newStatus === 'online' ? 'success' : 'failure',
            'device_ip' => $ip,
            'request' => [
                'operation' => 'device_status_poll',
                'target' => $ip,
                'summary' => 'Completed device provisioning poll cycle.',
            ],
            'response' => [
                'status' => $newStatus,
                'summary' => $newStatus === 'online'
                    ? 'Device is reachable and status data was refreshed.'
                    : 'Device did not respond to the live provisioning probes.',
            ],
            'reason' => $newStatus === 'online' ? null : 'TCP and ICMP discovery both failed during the current poll cycle.',
        ]));
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


    private function resolveEffectiveOnlineState(Device $device, bool $probeOnline, string $ip, string $probeMethod): bool
    {
        $failureKey = 'device_probe_failures:' . $device->id;
        $wasOnline = strtolower((string) ($device->status ?? 'offline')) === 'online';

        if ($probeOnline) {
            Cache::forget($failureKey);
            ProvisioningTrace::communication('status poll trace: probe succeeded', $this->pollTraceContext($device, [
                'layer' => 'error_handling',
                'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : 'TCP'),
                'state' => 'success',
                'device_ip' => $ip,
                'response' => [
                    'status' => 'reachable',
                    'summary' => 'Failure counter cleared after a successful probe.',
                ],
            ]));
            return true;
        }

        $failures = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, now()->addSeconds(self::PROBE_FAILURE_TTL_SECONDS));

        if ($wasOnline && $failures < self::OFFLINE_FAILURE_THRESHOLD) {
            ProvisioningTrace::communication('status poll trace: retaining online state during retry window', $this->pollTraceContext($device, [
                'layer' => 'error_handling',
                'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : ($probeMethod === 'tcp' ? 'TCP' : 'INTERNAL')),
                'state' => 'warning',
                'device_ip' => $ip,
                'reason' => 'Device missed one probe but has not crossed the offline retry threshold yet.',
                'retry' => [
                    'attempt' => $failures,
                    'max' => self::OFFLINE_FAILURE_THRESHOLD,
                ],
                'response' => [
                    'status' => 'retrying',
                    'summary' => 'Keeping the device online until the retry threshold is reached.',
                ],
            ]));
            return true;
        }

        ProvisioningTrace::communication('status poll trace: offline threshold reached', $this->pollTraceContext($device, [
            'layer' => 'error_handling',
            'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : ($probeMethod === 'tcp' ? 'TCP' : 'INTERNAL')),
            'state' => 'failure',
            'device_ip' => $ip,
            'reason' => 'Probe retry threshold reached; device is now considered offline.',
            'retry' => [
                'attempt' => $failures,
                'max' => self::OFFLINE_FAILURE_THRESHOLD,
            ],
            'response' => [
                'status' => 'offline',
                'summary' => 'No successful response was received before the retry threshold elapsed.',
            ],
        ]));
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

    private function tcpProbe(Device $device, string $ip, array $ports): bool
    {
        $ports = array_values(array_unique(array_filter($ports, static fn ($port) => is_int($port) && $port > 0)));
        $maxAttempts = max(1, count($ports));

        foreach ($ports as $index => $port) {
            $errno = 0;
            $errstr = '';
            $startedAt = microtime(true);
            $socket = @fsockopen($ip, $port, $errno, $errstr, 0.8);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            ProvisioningTrace::communication(
                $socket ? 'status poll trace: tcp probe succeeded' : 'status poll trace: tcp probe failed',
                $this->pollTraceContext($device, [
                    'layer' => 'network_discovery',
                    'protocol' => 'TCP',
                    'state' => $socket ? 'success' : 'warning',
                    'device_ip' => $ip,
                    'latency_ms' => $latencyMs,
                    'request' => [
                        'operation' => 'tcp_connect',
                        'target' => $ip . ':' . $port,
                        'summary' => "TCP connect attempt to {$ip}:{$port}",
                    ],
                    'response' => [
                        'status' => $socket ? 'connected' : 'failed',
                        'summary' => $socket
                            ? "TCP connection opened on {$ip}:{$port}"
                            : ($errstr !== '' ? $errstr : "TCP connection failed on {$ip}:{$port}"),
                    ],
                    'reason' => $socket ? null : ($errstr !== '' ? $errstr : ($errno !== 0 ? "TCP error {$errno}" : 'TCP probe failed without an OS error message.')),
                    'retry' => [
                        'attempt' => $index + 1,
                        'max' => $maxAttempts,
                    ],
                ])
            );

            if ($socket) {
                fclose($socket);
                return true;
            }
        }
        return false;
    }

    private function pingHost(Device $device, string $ip): bool
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
        $result = ProvisioningTrace::runProcess($process, [
            'label' => 'status poll trace',
            'line_prefix' => 'status poll trace',
            'log_output' => false,
            'context' => [
                'trace' => 'status polling',
                'probe_step' => 'ping',
                'device_ip' => $ip,
                'command' => $command,
            ],
        ]);

        ProvisioningTrace::communication(
            $result['ok'] ? 'status poll trace: icmp probe succeeded' : 'status poll trace: icmp probe failed',
            $this->pollTraceContext($device, [
                'layer' => 'network_discovery',
                'protocol' => 'ICMP',
                'state' => $result['ok'] ? 'success' : 'failure',
                'device_ip' => $ip,
                'latency_ms' => $result['duration_ms'] ?? null,
                'request' => [
                    'operation' => 'ping',
                    'target' => $ip,
                    'summary' => 'ICMP echo request',
                ],
                'response' => [
                    'status' => $result['ok'] ? 'reachable' : 'unreachable',
                    'summary' => ProvisioningTrace::summarizeText($result['output'] ?? '') ?? ($result['ok'] ? 'Ping completed successfully.' : 'Ping failed.'),
                ],
                'reason' => $result['ok'] ? null : (ProvisioningTrace::summarizeText($result['output'] ?? '') ?? 'Ping failed or timed out.'),
            ])
        );

        return $result['ok'];
    }

    private function fetchSnmpData(Device $device, string $ip): array
    {
        if (!function_exists('snmp2_get') && !function_exists('snmpget')) {
            ProvisioningTrace::communication('status poll trace: SNMP extension missing', $this->pollTraceContext($device, [
                'layer' => 'data_polling',
                'protocol' => 'SNMP',
                'state' => 'failure',
                'device_ip' => $ip,
                'reason' => 'PHP SNMP extension is not installed on this host.',
                'response' => [
                    'status' => 'skipped',
                    'summary' => 'SNMP polling could not start because the SNMP extension is unavailable.',
                ],
                'failure_hints' => ['runtime_extension'],
            ]));
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
        $uptimeResult = $this->snmpGet($device, $version, $host, $community, $uptimeOid, $timeout, $retries);
        $uptime = $this->parseSnmpValue($uptimeResult['value'] ?? null);
        $this->traceSnmpParsing($device, $ip, $uptimeOid, $uptimeResult['value'] ?? null, $uptime, 'uptime');

        $temperature = null;
        $type = strtoupper((string) ($device->type ?? ''));
        if ($type === 'CISCO') {
            $tempOids = [
                '1.3.6.1.4.1.9.2.1.58.0',
                '1.3.6.1.4.1.9.9.13.1.3.1.3.1',
            ];
            foreach ($tempOids as $oid) {
                $tempResult = $this->snmpGet($device, $version, $host, $community, $oid, $timeout, $retries);
                $tempParsed = $this->parseSnmpValue($tempResult['value'] ?? null, true);
                $this->traceSnmpParsing($device, $ip, $oid, $tempResult['value'] ?? null, $tempParsed, 'temperature');
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

    private function snmpGet(Device $device, string $version, string $host, string $community, string $oid, int $timeout, int $retries): array
    {
        $warningMessage = null;
        $startedAt = microtime(true);
        $attemptMax = max(1, $retries + 1);

        ProvisioningTrace::communication('status poll trace: SNMP request sent', $this->pollTraceContext($device, [
            'layer' => 'data_polling',
            'protocol' => 'SNMP',
            'state' => 'running',
            'device_ip' => strtok($host, ':') ?: $host,
            'request' => [
                'operation' => 'snmp_get',
                'target' => $host,
                'resource' => $oid,
                'summary' => "SNMP v{$version} GET {$oid} (community masked)",
            ],
            'retry' => [
                'attempt' => 1,
                'max' => $attemptMax,
            ],
        ]));

        try {
            set_error_handler(static function (int $severity, string $message) use (&$warningMessage): bool {
                $warningMessage = $message;
                return true;
            });

            if ($version === '1' && function_exists('snmpget')) {
                $value = @snmpget($host, $community, $oid, $timeout, $retries);
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                restore_error_handler();
                return $this->handleSnmpResult($device, $host, $oid, $value !== false ? (string) $value : null, $warningMessage, $latencyMs);
            }
            if (function_exists('snmp2_get')) {
                $value = @snmp2_get($host, $community, $oid, $timeout, $retries);
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                restore_error_handler();
                return $this->handleSnmpResult($device, $host, $oid, $value !== false ? (string) $value : null, $warningMessage, $latencyMs);
            }
        } catch (\Throwable $e) {
            restore_error_handler();
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            ProvisioningTrace::communication('status poll trace: SNMP request failed', $this->pollTraceContext($device, [
                'layer' => 'error_handling',
                'protocol' => 'SNMP',
                'state' => 'failure',
                'device_ip' => strtok($host, ':') ?: $host,
                'latency_ms' => $latencyMs,
                'request' => [
                    'operation' => 'snmp_get',
                    'target' => $host,
                    'resource' => $oid,
                    'summary' => "SNMP GET {$oid}",
                ],
                'response' => [
                    'status' => 'exception',
                    'summary' => ProvisioningTrace::summarizeText($e->getMessage()) ?? 'SNMP request threw an exception.',
                ],
                'reason' => $e->getMessage(),
            ]));

            return [
                'value' => null,
                'latency_ms' => $latencyMs,
                'error' => $e->getMessage(),
            ];
        }

        restore_error_handler();
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return $this->handleSnmpResult($device, $host, $oid, null, $warningMessage ?: 'SNMP function is unavailable for the requested version.', $latencyMs);
    }

    private function handleSnmpResult(Device $device, string $host, string $oid, ?string $value, ?string $warningMessage, int $latencyMs): array
    {
        $deviceIp = strtok($host, ':') ?: $host;

        if ($value !== null) {
            ProvisioningTrace::communication('status poll trace: SNMP response received', $this->pollTraceContext($device, [
                'layer' => 'data_polling',
                'protocol' => 'SNMP',
                'state' => 'success',
                'device_ip' => $deviceIp,
                'latency_ms' => $latencyMs,
                'request' => [
                    'operation' => 'snmp_get',
                    'target' => $host,
                    'resource' => $oid,
                    'summary' => "SNMP response received for {$oid}",
                ],
                'response' => [
                    'status' => 'ok',
                    'summary' => ProvisioningTrace::summarizeText($value) ?? 'SNMP returned a value.',
                    'payload_summary' => ProvisioningTrace::summarizeText($value),
                ],
            ]));

            return [
                'value' => $value,
                'latency_ms' => $latencyMs,
                'error' => null,
            ];
        }

        $reason = $warningMessage ?: 'No SNMP response was received.';
        ProvisioningTrace::communication('status poll trace: SNMP request failed', $this->pollTraceContext($device, [
            'layer' => 'error_handling',
            'protocol' => 'SNMP',
            'state' => 'failure',
            'device_ip' => $deviceIp,
            'latency_ms' => $latencyMs,
            'request' => [
                'operation' => 'snmp_get',
                'target' => $host,
                'resource' => $oid,
                'summary' => "SNMP request failed for {$oid}",
            ],
            'response' => [
                'status' => 'failed',
                'summary' => ProvisioningTrace::summarizeText($reason) ?? 'SNMP request failed.',
            ],
            'reason' => $reason,
        ]));

        return [
            'value' => null,
            'latency_ms' => $latencyMs,
            'error' => $reason,
        ];
    }

    private function traceSnmpParsing(Device $device, string $ip, string $oid, ?string $rawValue, ?string $parsedValue, string $field): void
    {
        if ($rawValue === null && $parsedValue === null) {
            return;
        }

        ProvisioningTrace::communication(
            $parsedValue !== null ? 'status poll trace: SNMP response parsed' : 'status poll trace: SNMP response parse warning',
            $this->pollTraceContext($device, [
                'layer' => 'response_parsing',
                'protocol' => 'SNMP',
                'state' => $parsedValue !== null ? 'success' : 'warning',
                'device_ip' => $ip,
                'request' => [
                    'operation' => 'snmp_parse',
                    'target' => $ip,
                    'resource' => $oid,
                    'summary' => "Parsing SNMP field {$field} from {$oid}",
                ],
                'response' => [
                    'status' => $parsedValue !== null ? 'parsed' : 'unparsed',
                    'summary' => $parsedValue !== null
                        ? "Parsed {$field} value {$parsedValue}"
                        : 'SNMP response was received but could not be normalized.',
                    'payload_summary' => ProvisioningTrace::summarizeText($rawValue),
                ],
                'reason' => $parsedValue !== null ? null : 'SNMP response parsing returned no normalized value.',
            ])
        );
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

        $this->logProvisioning('device poll: uptime helper candidates resolved', $this->pollTraceContext($device, [
            'probe_step' => 'fetch_uptime',
            'device_ip' => $ip,
            'model_known' => $modelKnown,
            'is_nexus' => $isNexus,
            'candidates' => $candidates,
        ]));

        foreach ($candidates as $script) {
            $scriptPath = base_path('scripts/' . $script);
            if (!is_file($scriptPath)) {
                $this->logProvisioning('device poll: uptime helper script missing', $this->pollTraceContext($device, [
                    'probe_step' => 'fetch_uptime',
                    'script_name' => $script,
                    'script_path' => $scriptPath,
                ]));
                continue;
            }
            $timeout = $script === 'showuptime_nexus.sh' ? 90 : 40;
            $process = new Process(['bash', $scriptPath], base_path());
            $process->setTimeout($timeout);
            $process->setEnv(array_merge($_ENV, $_SERVER, ProvisioningTrace::childProcessEnv(), $env));
            $result = ProvisioningTrace::runProcess($process, [
                'label' => 'status poll trace',
                'line_prefix' => 'status poll trace',
                'context' => $this->pollTraceContext($device, [
                    'probe_step' => 'fetch_uptime',
                    'layer' => 'authentication_handshake',
                    'protocol' => 'TELNET',
                    'script_name' => $script,
                    'script_path' => $scriptPath,
                    'device_ip' => $ip,
                    'command' => ['bash', $scriptPath],
                ]),
                'secret_values' => array_values(array_filter([$password, $enable])),
                'env_keys' => array_values(array_keys($env)),
            ]);
            if (!$result['ok']) {
                continue;
            }

            $output = $this->sanitizeUtf8(trim((string) ($result['stdout'] !== '' ? $result['stdout'] : $result['output'])));
            if ($output === '' || $output === 'N/A') {
                continue;
            }

            $this->logProvisioning('device poll: uptime helper returned value', $this->pollTraceContext($device, [
                'probe_step' => 'fetch_uptime',
                'script_name' => $script,
                'output_present' => true,
            ]));

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

        $this->logProvisioning('device poll: temperature helper candidates resolved', $this->pollTraceContext($device, [
            'probe_step' => 'fetch_temperature',
            'device_ip' => $ip,
            'model_known' => $modelKnown,
            'is_nexus' => $isNexus,
            'is_3560' => $is3560,
            'candidates' => $candidates,
        ]));

        foreach ($candidates as $script) {
            $scriptPath = base_path('scripts/' . $script);
            if (!is_file($scriptPath)) {
                $this->logProvisioning('device poll: temperature helper script missing', $this->pollTraceContext($device, [
                    'probe_step' => 'fetch_temperature',
                    'script_name' => $script,
                    'script_path' => $scriptPath,
                ]));
                continue;
            }
            $timeout = $script === 'showtemp_nexus.sh' ? 90 : 40;
            $process = new Process(['bash', $scriptPath], base_path());
            $process->setTimeout($timeout);
            $process->setEnv(array_merge($_ENV, $_SERVER, ProvisioningTrace::childProcessEnv(), $env));
            $result = ProvisioningTrace::runProcess($process, [
                'label' => 'status poll trace',
                'line_prefix' => 'status poll trace',
                'context' => $this->pollTraceContext($device, [
                    'probe_step' => 'fetch_temperature',
                    'layer' => 'authentication_handshake',
                    'protocol' => 'TELNET',
                    'script_name' => $script,
                    'script_path' => $scriptPath,
                    'device_ip' => $ip,
                    'command' => ['bash', $scriptPath],
                ]),
                'secret_values' => array_values(array_filter([$password, $enable])),
                'env_keys' => array_values(array_keys($env)),
            ]);
            if (!$result['ok']) {
                continue;
            }

            $output = $this->sanitizeUtf8(trim((string) ($result['stdout'] !== '' ? $result['stdout'] : $result['output'])));
            if ($output === '' || $output === 'N/A') {
                continue;
            }

            $this->logProvisioning('device poll: temperature helper returned value', $this->pollTraceContext($device, [
                'probe_step' => 'fetch_temperature',
                'script_name' => $script,
                'output_present' => true,
            ]));

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

    private function pollTraceContext(Device $device, array $context = []): array
    {
        return $context + [
            'trace' => 'status polling',
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_type' => $device->type,
        ];
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
















