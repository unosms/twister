<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProvisioningTrace
{
    public static function enabled(): bool
    {
        return (bool) Cache::get('provisioning_log_enabled', false);
    }

    public static function log(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/provisioning.log'),
                'level' => 'debug',
            ])->debug($message, self::withoutSecrets($context));
        } catch (\Throwable $e) {
            // ignore logging failures
        }
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

        self::log($label . ': launching', $context + [
            'timeout_seconds' => $process->getTimeout(),
            'env_keys' => array_values(array_map('strval', $options['env_keys'] ?? [])),
        ]);

        try {
            if ($logOutput) {
                $process->run(function (string $type, string $buffer) use (&$partialLines, $secretValues, $context, $linePrefix): void {
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
                    }
                });
            } else {
                $process->run();
            }
        } catch (\Throwable $e) {
            self::log($label . ': exception', $context + [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'exit_code' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
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
            }
        }

        $stdout = self::redactText($process->getOutput(), $secretValues);
        $stderr = self::redactText($process->getErrorOutput(), $secretValues);
        $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $exitCode = $process->getExitCode();

        self::log(
            $label . ($process->isSuccessful() ? ': completed' : ': failed'),
            $context + [
                'duration_ms' => $durationMs,
                'exit_code' => $exitCode,
            ]
        );

        return [
            'ok' => $process->isSuccessful(),
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

        return $context;
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
}
