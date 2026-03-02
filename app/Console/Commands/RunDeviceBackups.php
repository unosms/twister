<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunDeviceBackups extends Command
{
    protected $signature = 'devices:run-backups {--device=} {--limit=}';

    protected $description = 'Run config backups for Cisco devices.';

    public function handle(): int
    {
        $deviceOption = (int) ($this->option('device') ?? 0);
        $limit = (int) ($this->option('limit') ?? 0);

        $this->writeProvisioningLog('backup trace: scheduled backup command started', [
            'trigger' => 'devices:run-backups',
            'device_option' => $deviceOption > 0 ? $deviceOption : null,
            'limit_option' => $limit > 0 ? $limit : null,
        ]);

        $query = Device::query()->orderBy('id');

        if ($deviceOption > 0) {
            $query->where('id', $deviceOption);
        } elseif ($limit > 0) {
            $query->limit($limit);
        }

        $devices = $query->get();
        if ($devices->isEmpty()) {
            $this->writeProvisioningLog('backup trace: scheduled backup command found no devices', [
                'trigger' => 'devices:run-backups',
            ]);
            $this->warn('No matching devices found.');
            return self::SUCCESS;
        }

        $ran = 0;
        $ok = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($devices as $device) {
            $result = $this->runBackupForDevice($device);

            if ($result['status'] === 'skipped') {
                $skipped++;
                $this->line("[SKIP] Device {$device->id} {$device->name}: {$result['message']}");
                continue;
            }

            $ran++;
            if ($result['status'] === 'ok') {
                $ok++;
                $this->info("[OK] Device {$device->id} {$device->name}: {$result['message']}");
            } else {
                $failed++;
                $this->error("[FAIL] Device {$device->id} {$device->name}: {$result['message']}");
            }
        }

        $this->line("Backups attempted: {$ran}, successful: {$ok}, failed: {$failed}, skipped: {$skipped}.");
        $this->writeProvisioningLog('backup trace: scheduled backup command finished', [
            'trigger' => 'devices:run-backups',
            'attempted' => $ran,
            'successful' => $ok,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runBackupForDevice(Device $device): array
    {
        $meta = $this->normalizeMetadata($device->metadata);
        $cisco = data_get($meta, 'cisco', []);
        $traceContext = $this->backupTraceContext($device, [
            'trigger' => 'devices:run-backups',
        ]);

        $this->writeProvisioningLog('backup trace: scheduled backup evaluation started', $traceContext);

        $type = strtoupper((string) ($device->type ?? ''));
        if ($type !== 'CISCO' || !is_array($cisco) || empty($cisco)) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - invalid Cisco metadata', $traceContext);
            return [
                'status' => 'skipped',
                'message' => 'not a Cisco device or missing Cisco metadata',
            ];
        }

        if (!empty($meta['monitoring_disabled'])) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - device deactivated', $traceContext);
            return [
                'status' => 'skipped',
                'message' => 'device is deactivated',
            ];
        }

        $switchModel = strtoupper((string) ($cisco['switch_model'] ?? ($meta['subtype'] ?? $device->model ?? '')));
        $isNexus = str_contains($switchModel, 'NEXUS');

        $ip = $this->firstNonEmpty($cisco['ip_address'] ?? null, $device->ip_address);
        $password = $this->decryptValue($cisco['password'] ?? null);
        $username = $this->firstNonEmpty($cisco['username'] ?? null, $cisco['user'] ?? null);
        $enablePassword = $this->decryptValue($cisco['enable_password'] ?? null);
        $location = $this->resolveFolderLocation($device, $cisco);

        $this->writeProvisioningLog('backup trace: scheduled backup inputs resolved', $traceContext + [
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'switch_ip' => $ip,
            'location' => $location,
            'password_present' => $password !== null && $password !== '',
            'enable_password_present' => $enablePassword !== null && $enablePassword !== '',
            'username_present' => $username !== null && $username !== '',
        ]);

        if (!$ip || !$password) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing IP or password', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing IP or password',
            ];
        }

        if ($isNexus && !$username) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing Nexus username', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing username for Nexus backup',
            ];
        }

        if (!$isNexus && !$enablePassword) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing enable password', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing enable password for backup',
            ];
        }

        $script = $isNexus ? 'nexus_backup.sh' : '4948_backup.sh';
        $scriptPath = base_path('scripts/' . $script);
        if (!is_file($scriptPath)) {
            $this->writeProvisioningLog('backup trace: scheduled backup script missing', $traceContext + [
                'script_name' => $script,
            ]);
            return [
                'status' => 'failed',
                'message' => "script not found: {$script}",
            ];
        }

        $command = $isNexus
            ? ['bash', $scriptPath, $ip, $username, $password, $location]
            : ['bash', $scriptPath, $ip, $password, $enablePassword, $location];

        $result = $this->runLoggedProcess($command, $traceContext + [
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'switch_ip' => $ip,
            'location' => $location,
            'script_name' => $script,
            'script_path' => $scriptPath,
            'command' => $this->redactBackupCommand($command, $isNexus),
            'secret_values' => array_values(array_filter($isNexus ? [$password] : [$password, $enablePassword])),
        ]);

        if (!$result['ok']) {
            return [
                'status' => 'failed',
                'message' => $result['output'] !== '' ? $result['output'] : 'backup script failed',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'backup completed',
        ];
    }

    private function resolveFolderLocation(Device $device, array $cisco): string
    {
        $location = $this->firstNonEmpty(
            $cisco['folder_location'] ?? null,
            data_get($device->metadata, 'folder_location')
        );

        if ($location) {
            return $location;
        }

        $baseName = $this->firstNonEmpty($cisco['name'] ?? null, $device->name, 'device');
        $slug = preg_replace('/\s+/', '_', trim((string) $baseName));

        return 'uno/' . ($slug !== '' ? $slug : 'device');
    }

    private function normalizeMetadata($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function firstNonEmpty(...$values): ?string
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
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

    private function provisioningLogEnabled(): bool
    {
        return (bool) cache()->get('provisioning_log_enabled', false);
    }

    private function writeProvisioningLog(string $message, array $context = []): void
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

    private function backupTraceContext(Device $device, array $context = []): array
    {
        return $context + [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_type' => $device->type,
        ];
    }

    private function redactBackupCommand(array $command, bool $isNexus): array
    {
        $redacted = $command;

        if ($isNexus) {
            if (isset($redacted[4])) {
                $redacted[4] = '[REDACTED]';
            }
        } else {
            if (isset($redacted[3])) {
                $redacted[3] = '[REDACTED]';
            }

            if (isset($redacted[4])) {
                $redacted[4] = '[REDACTED]';
            }
        }

        return $redacted;
    }

    private function runLoggedProcess(array $command, array $traceContext): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout(180);
        $startedAt = microtime(true);
        $logContext = $this->withoutProvisioningSecrets($traceContext);
        $secretValues = array_values(array_filter($traceContext['secret_values'] ?? []));
        $partialLines = ['stdout' => '', 'stderr' => ''];

        $this->writeProvisioningLog('backup trace: launching process', $logContext + [
            'timeout_seconds' => 180,
        ]);

        try {
            $process->run(function (string $type, string $buffer) use (&$partialLines, $secretValues, $logContext): void {
                $stream = $type === Process::ERR ? 'stderr' : 'stdout';
                $partialLines[$stream] .= $this->redactProvisioningText($buffer, $secretValues);
                $normalized = str_replace(["\r\n", "\r"], "\n", $partialLines[$stream]);
                $lines = explode("\n", $normalized);
                $partialLines[$stream] = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $this->writeProvisioningLog("backup trace {$stream}: {$line}", $logContext);
                }
            });
        } catch (\Throwable $e) {
            $this->writeProvisioningLog('backup trace: process threw an exception', $logContext + [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'output' => $e->getMessage(),
            ];
        }

        foreach ($partialLines as $stream => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $this->writeProvisioningLog("backup trace {$stream}: {$line}", $logContext);
        }

        $payload = trim($this->redactProvisioningText($process->getOutput() . "\n" . $process->getErrorOutput(), $secretValues));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (!$process->isSuccessful()) {
            $this->writeProvisioningLog('backup trace: process failed', $logContext + [
                'duration_ms' => $durationMs,
                'exit_code' => $process->getExitCode(),
            ]);

            return [
                'ok' => false,
                'output' => $payload,
            ];
        }

        $this->writeProvisioningLog('backup trace: process completed', $logContext + [
            'duration_ms' => $durationMs,
            'exit_code' => $process->getExitCode(),
        ]);

        return [
            'ok' => true,
            'output' => $payload,
        ];
    }

    private function withoutProvisioningSecrets(array $context): array
    {
        unset($context['secret_values']);

        return $context;
    }

    private function redactProvisioningText(string $text, array $secretValues): string
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
