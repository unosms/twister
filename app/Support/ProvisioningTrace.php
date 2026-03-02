<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProvisioningTrace
{
    public static function enabled(): bool
    {
        if (Cache::has('provisioning_log_enabled')) {
            return (bool) Cache::get('provisioning_log_enabled', false);
        }

        return (bool) config('provisioning.enabled', false);
    }

    public static function streamEnabled(): bool
    {
        return (bool) config('provisioning.sse_enabled', true);
    }

    public static function traceLevel(): string
    {
        $level = strtolower(trim((string) config('provisioning.trace_level', 'trace')));

        return in_array($level, ['summary', 'debug', 'trace'], true) ? $level : 'trace';
    }

    public static function allowsTraceLevel(string $level): bool
    {
        $levels = [
            'summary' => 1,
            'debug' => 2,
            'trace' => 3,
        ];

        return ($levels[self::traceLevel()] ?? 3) >= ($levels[strtolower(trim($level))] ?? 3);
    }

    public static function rawLogPath(): string
    {
        return (string) config('provisioning.raw_log_path', storage_path('logs/provisioning.log'));
    }

    public static function eventLogPath(): string
    {
        return (string) config('provisioning.event_log_path', storage_path('logs/provisioning-events.jsonl'));
    }

    public static function tailLimit(): int
    {
        return max(1, min((int) config('provisioning.tail_limit', 200), 2000));
    }

    public static function eventLimit(): int
    {
        return max(1, min((int) config('provisioning.event_limit', 80), 500));
    }

    public static function childProcessEnv(array $overrides = []): array
    {
        return array_merge([
            'PROVISIONING_LOG_ENABLED' => self::enabled() ? '1' : '0',
            'PROVISIONING_TRACE_LEVEL' => self::traceLevel(),
            'PROVISIONING_TRACE_STDOUT' => self::enabled() && self::allowsTraceLevel('trace') ? '1' : '0',
            'PROVISIONING_TEXT_LOG' => self::rawLogPath(),
            'PROVISIONING_EVENTS_LOG' => self::eventLogPath(),
        ], $overrides);
    }

    public static function log(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            Log::build([
                'driver' => 'single',
                'path' => self::rawLogPath(),
                'level' => 'debug',
            ])->debug($message, self::withoutSecrets($context));
        } catch (\Throwable $e) {
            // ignore logging failures
        }
    }

    public static function communication(string $message, array $event = []): void
    {
        if (!self::enabled()) {
            return;
        }

        $normalized = self::normalizeEvent($event + ['message' => $message]);

        self::log($message, array_filter([
            'trace' => $normalized['trace_key'],
            'layer' => $normalized['layer_key'],
            'protocol' => $normalized['protocol'],
            'state' => $normalized['state'],
            'device_id' => $normalized['device_id'],
            'device_name' => $normalized['device_name'],
            'device_ip' => $normalized['device_ip'],
            'script_name' => $normalized['script_name'],
            'request' => $normalized['request']['summary'] ?? null,
            'response' => $normalized['response']['summary'] ?? null,
            'latency_ms' => $normalized['latency_ms'],
            'reason' => $normalized['reason'],
            'retry_attempt' => data_get($normalized, 'retry.attempt'),
            'retry_max' => data_get($normalized, 'retry.max'),
            'failure_hints' => $normalized['failure_hints'],
        ], static fn ($value) => $value !== null && $value !== [] && $value !== ''));

        self::writeEvent($normalized);
    }

    public static function runProcess(Process $process, array $options = []): array
    {
        if (!self::enabled()) {
            try {
                $process->run();
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'exit_code' => null,
                    'duration_ms' => 0,
                    'output' => $e->getMessage(),
                    'stdout' => '',
                    'stderr' => '',
                ];
            }

            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            return [
                'ok' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'duration_ms' => 0,
                'output' => trim($stdout . ($stderr !== '' ? "\n" . $stderr : '')),
                'stdout' => $stdout,
                'stderr' => $stderr,
            ];
        }

        $context = self::withoutSecrets($options['context'] ?? []);
        $secretValues = array_values(array_filter($options['secret_values'] ?? []));
        $label = trim((string) ($options['label'] ?? 'process trace'));
        $linePrefix = trim((string) ($options['line_prefix'] ?? $label));
        $logOutput = (bool) ($options['log_output'] ?? true);
        $startedAt = microtime(true);
        $partialLines = ['stdout' => '', 'stderr' => ''];
        $command = $context['command'] ?? preg_split('/\s+/', trim($process->getCommandLine())) ?: [];
        $protocol = self::normalizeProtocol($context['protocol'] ?? self::inferProtocol($command));
        $trace = self::resolveTraceLabel($context['trace'] ?? $label);
        $layer = self::normalizeLayer($context['layer'] ?? 'process_execution');

        self::log($label . ': launching', $context + [
            'timeout_seconds' => $process->getTimeout(),
            'env_keys' => array_values(array_map('strval', $options['env_keys'] ?? [])),
        ]);

        self::communication($label . ': launching', $context + [
            'trace' => $trace,
            'layer' => $layer,
            'protocol' => $protocol,
            'state' => 'running',
            'request' => [
                'operation' => 'process.run',
                'target' => (string) ($context['script_path'] ?? ($command[0] ?? 'process')),
                'summary' => self::summarizeText(is_array($command) ? implode(' ', array_map('strval', $command)) : (string) $command),
            ],
            'response' => [
                'status' => 'launching',
                'summary' => 'Process started.',
            ],
        ]);

        try {
            if ($logOutput) {
                $process->run(function (string $type, string $buffer) use (&$partialLines, $secretValues, $context, $linePrefix, $protocol, $trace): void {
                    $stream = $type === Process::ERR ? 'stderr' : 'stdout';
                    $partialLines[$stream] .= self::redactText($buffer, $secretValues);
                    $normalized = str_replace(["\r\n", "\r"], "\n", $partialLines[$stream]);
                    $lines = explode("\n", $normalized);
                    $partialLines[$stream] = array_pop($lines);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }

                        self::log("{$linePrefix} {$stream}: {$line}", $context);

                        if (self::allowsTraceLevel('trace')) {
                            self::communication("{$linePrefix} {$stream}: {$line}", $context + [
                                'trace' => $trace,
                                'layer' => 'process_output',
                                'protocol' => $protocol,
                                'state' => $stream === 'stderr' ? 'warning' : 'running',
                                'response' => [
                                    'status' => $stream,
                                    'summary' => self::summarizeText($line),
                                ],
                            ]);
                        }
                    }
                });
            } else {
                $process->run();
            }
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            self::log($label . ': exception', $context + [
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            self::communication($label . ': exception', $context + [
                'trace' => $trace,
                'layer' => 'error_handling',
                'protocol' => $protocol,
                'state' => 'failure',
                'latency_ms' => $durationMs,
                'reason' => $e->getMessage(),
                'response' => [
                    'status' => 'exception',
                    'summary' => self::summarizeText($e->getMessage()),
                ],
            ]);

            return [
                'ok' => false,
                'exit_code' => null,
                'duration_ms' => $durationMs,
                'output' => $e->getMessage(),
                'stdout' => '',
                'stderr' => '',
            ];
        }

        if ($logOutput) {
            foreach ($partialLines as $stream => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                self::log("{$linePrefix} {$stream}: {$line}", $context);

                if (self::allowsTraceLevel('trace')) {
                    self::communication("{$linePrefix} {$stream}: {$line}", $context + [
                        'trace' => $trace,
                        'layer' => 'process_output',
                        'protocol' => $protocol,
                        'state' => $stream === 'stderr' ? 'warning' : 'running',
                        'response' => [
                            'status' => $stream,
                            'summary' => self::summarizeText($line),
                        ],
                    ]);
                }
            }
        }

        $stdout = self::redactText($process->getOutput(), $secretValues);
        $stderr = self::redactText($process->getErrorOutput(), $secretValues);
        $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $exitCode = $process->getExitCode();
        $wasSuccessful = $process->isSuccessful();

        self::log(
            $label . ($wasSuccessful ? ': completed' : ': failed'),
            $context + [
                'duration_ms' => $durationMs,
                'exit_code' => $exitCode,
            ]
        );

        self::communication($label . ($wasSuccessful ? ': completed' : ': failed'), $context + [
            'trace' => $trace,
            'layer' => $wasSuccessful ? $layer : 'error_handling',
            'protocol' => $protocol,
            'state' => $wasSuccessful ? 'success' : 'failure',
            'latency_ms' => $durationMs,
            'reason' => $wasSuccessful ? null : self::summarizeText($stderr !== '' ? $stderr : $output),
            'response' => [
                'status' => $wasSuccessful ? 'completed' : 'failed',
                'summary' => self::summarizeText($stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : $output)),
                'payload_summary' => self::summarizeText($output),
            ],
            'metrics' => [
                'exit_code' => $exitCode,
            ],
        ]);

        return [
            'ok' => $wasSuccessful,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'output' => $output,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    public static function withoutSecrets(array $context): array
    {
        unset($context['secret_values'], $context['label'], $context['line_prefix'], $context['log_output']);

        return self::sanitizeArray($context);
    }

    public static function redactText(string $text, array $secretValues): string
    {
        foreach ($secretValues as $secret) {
            if (!is_string($secret) || $secret === '') {
                continue;
            }

            $text = str_replace($secret, '[REDACTED]', $text);
        }

        return $text;
    }

    public static function summarizeText(?string $text, ?int $limit = null): ?string
    {
        if ($text === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($value === '') {
            return null;
        }

        $limit ??= max(128, (int) config('provisioning.max_payload_bytes', 2048));

        return Str::limit($value, $limit);
    }

    private static function writeEvent(array $event): void
    {
        try {
            $path = self::eventLogPath();
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            file_put_contents(
                $path,
                json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            // ignore logging failures
        }
    }

    private static function normalizeEvent(array $event): array
    {
        $state = self::normalizeState($event['state'] ?? 'running');
        $protocol = self::normalizeProtocol($event['protocol'] ?? 'internal');
        $traceKey = self::normalizeTraceKey($event['trace'] ?? 'provisioning');
        $layerKey = self::normalizeLayer($event['layer'] ?? 'internal');
        $message = self::summarizeText((string) ($event['message'] ?? 'Provisioning event'));
        $reason = self::summarizeText($event['reason'] ?? null);

        $request = self::normalizeRequestResponse($event['request'] ?? []);
        $response = self::normalizeRequestResponse($event['response'] ?? []);
        $retry = self::normalizeRetry($event['retry'] ?? []);
        $metrics = self::sanitizeArray($event['metrics'] ?? []);
        $failureHints = array_values(array_unique(array_filter(array_merge(
            array_map('strval', $event['failure_hints'] ?? []),
            self::detectFailureHints($protocol, $reason, $response['summary'] ?? null)
        ))));

        return array_filter([
            'id' => (string) ($event['id'] ?? Str::uuid()),
            'timestamp' => (string) ($event['timestamp'] ?? now()->toIso8601String()),
            'message' => $message,
            'state' => $state,
            'trace_key' => $traceKey,
            'trace' => self::resolveTraceLabel($traceKey),
            'layer_key' => $layerKey,
            'layer' => self::resolveLayerLabel($layerKey),
            'protocol' => $protocol,
            'device_id' => isset($event['device_id']) ? (int) $event['device_id'] : null,
            'device_name' => self::summarizeText($event['device_name'] ?? null, 255),
            'device_ip' => self::summarizeText($event['device_ip'] ?? null, 255),
            'device_hostname' => self::summarizeText($event['device_hostname'] ?? null, 255),
            'script_name' => self::summarizeText($event['script_name'] ?? null, 255),
            'request' => $request,
            'response' => $response,
            'latency_ms' => isset($event['latency_ms']) ? (int) $event['latency_ms'] : null,
            'reason' => $reason,
            'retry' => $retry,
            'metrics' => $metrics,
            'failure_hints' => $failureHints,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private static function normalizeRequestResponse(array $payload): array
    {
        if (!$payload) {
            return [];
        }

        return array_filter([
            'operation' => self::summarizeText($payload['operation'] ?? null, 120),
            'target' => self::summarizeText($payload['target'] ?? null, 255),
            'resource' => self::summarizeText($payload['resource'] ?? null, 255),
            'summary' => self::summarizeText($payload['summary'] ?? null),
            'status' => self::summarizeText($payload['status'] ?? null, 120),
            'payload_summary' => self::summarizeText($payload['payload_summary'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private static function normalizeRetry(array $retry): array
    {
        if (!$retry) {
            return [];
        }

        return array_filter([
            'attempt' => isset($retry['attempt']) ? (int) $retry['attempt'] : null,
            'max' => isset($retry['max']) ? (int) $retry['max'] : null,
        ], static fn ($value) => $value !== null);
    }

    private static function normalizeState(string $state): string
    {
        $state = strtolower(trim($state));

        return match ($state) {
            'completed', 'complete', 'ok' => 'success',
            'failed', 'error' => 'failure',
            default => in_array($state, ['running', 'success', 'warning', 'failure', 'info'], true) ? $state : 'info',
        };
    }

    private static function normalizeLayer(string $layer): string
    {
        $layer = strtolower(trim($layer));
        $layer = preg_replace('/[^a-z0-9_]+/', '_', $layer) ?? 'internal';

        return match ($layer) {
            'network_discovery',
            'authentication_handshake',
            'data_polling',
            'response_parsing',
            'error_handling',
            'process_execution',
            'process_output',
            'status_transition',
            'internal' => $layer,
            default => 'internal',
        };
    }

    private static function normalizeProtocol(string $protocol): string
    {
        $protocol = strtoupper(trim($protocol));

        return $protocol !== '' ? $protocol : 'INTERNAL';
    }

    private static function normalizeTraceKey(string $trace): string
    {
        $trace = strtolower(trim($trace));
        $trace = preg_replace('/[^a-z0-9_]+/', '_', $trace) ?? 'provisioning';

        return $trace !== '' ? $trace : 'provisioning';
    }

    private static function resolveTraceLabel(string $trace): string
    {
        return match ($trace) {
            'backup', 'backup_execution' => 'Backup',
            'command', 'command_execution' => 'Command',
            'status_poll', 'status_polling' => 'Status Poll',
            'device_probe' => 'Device Probe',
            'device_snapshot' => 'Device Snapshot',
            'events', 'events_polling' => 'Events',
            'poller', 'poller_execution' => 'Poller',
            'support', 'support_diagnostics' => 'Support',
            default => Str::title(str_replace('_', ' ', $trace)),
        };
    }

    private static function resolveLayerLabel(string $layer): string
    {
        return match ($layer) {
            'network_discovery' => 'Network Discovery',
            'authentication_handshake' => 'Authentication / Handshake',
            'data_polling' => 'Data Polling',
            'response_parsing' => 'Response Parsing',
            'error_handling' => 'Error Handling / Retry',
            'process_execution' => 'Process Execution',
            'process_output' => 'Process Output',
            'status_transition' => 'Status Transition',
            default => 'Internal',
        };
    }

    private static function sanitizeArray(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::sanitizeArray($value);
                if ($nested !== []) {
                    $sanitized[$key] = $nested;
                }
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_scalar($value)) {
                $stringValue = (string) $value;
                if (self::looksSensitive((string) $key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = self::summarizeText($stringValue);
                }
            }
        }

        return $sanitized;
    }

    private static function looksSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (['password', 'secret', 'token', 'community', 'authorization', 'cookie'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function detectFailureHints(string $protocol, ?string $reason, ?string $response): array
    {
        $haystack = strtolower(trim(($reason ?? '') . ' ' . ($response ?? '')));
        if ($haystack === '') {
            return [];
        }

        $hints = [];

        if (str_contains($haystack, 'permission denied') || str_contains($haystack, 'not writable')) {
            $hints[] = 'permissions';
        }
        if (str_contains($haystack, 'no route to host') || str_contains($haystack, 'network is unreachable')) {
            $hints[] = 'routing_or_nat';
        }
        if (str_contains($haystack, 'connection refused') || str_contains($haystack, 'actively refused')) {
            $hints[] = 'service_closed_or_firewall';
        }
        if (str_contains($haystack, 'timed out') || str_contains($haystack, 'timeout') || str_contains($haystack, 'no response from')) {
            $hints[] = 'timeout_or_firewall';
        }
        if (str_contains($haystack, 'auth') || str_contains($haystack, 'login incorrect') || str_contains($haystack, 'access denied')) {
            $hints[] = 'credentials';
        }
        if (str_contains($haystack, 'snmp') && (str_contains($haystack, 'version') || str_contains($haystack, 'community'))) {
            $hints[] = 'snmp_version_or_community';
        }
        if (in_array($protocol, ['HTTP', 'HTTPS'], true) && (str_contains($haystack, 'ssl') || str_contains($haystack, 'certificate') || str_contains($haystack, 'tls'))) {
            $hints[] = 'tls';
        }
        if (in_array($protocol, ['HTTP', 'HTTPS'], true) && str_contains($haystack, 'cors')) {
            $hints[] = 'cors';
        }
        if (str_contains($haystack, 'extension missing') || str_contains($haystack, 'undefined function')) {
            $hints[] = 'runtime_extension';
        }

        return $hints;
    }

    private static function inferProtocol(array $command): string
    {
        $haystack = strtolower(implode(' ', array_map('strval', $command)));

        return match (true) {
            str_contains($haystack, 'ping') => 'ICMP',
            str_contains($haystack, 'ssh') => 'SSH',
            str_contains($haystack, 'telnet') => 'TELNET',
            str_contains($haystack, 'curl') => str_contains($haystack, 'https') ? 'HTTPS' : 'HTTP',
            str_contains($haystack, 'snmp') => 'SNMP',
            default => 'LOCAL_PROCESS',
        };
    }
}
