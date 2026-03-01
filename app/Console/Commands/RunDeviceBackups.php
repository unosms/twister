<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunDeviceBackups extends Command
{
    protected $signature = 'devices:run-backups {--device=} {--limit=}';

    protected $description = 'Run config backups for Cisco devices.';

    public function handle(): int
    {
        $deviceOption = (int) ($this->option('device') ?? 0);
        $limit = (int) ($this->option('limit') ?? 0);

        $query = Device::query()->orderBy('id');

        if ($deviceOption > 0) {
            $query->where('id', $deviceOption);
        } elseif ($limit > 0) {
            $query->limit($limit);
        }

        $devices = $query->get();
        if ($devices->isEmpty()) {
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

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runBackupForDevice(Device $device): array
    {
        $meta = $this->normalizeMetadata($device->metadata);
        $cisco = data_get($meta, 'cisco', []);

        $type = strtoupper((string) ($device->type ?? ''));
        if ($type !== 'CISCO' || !is_array($cisco) || empty($cisco)) {
            return [
                'status' => 'skipped',
                'message' => 'not a Cisco device or missing Cisco metadata',
            ];
        }

        if (!empty($meta['monitoring_disabled'])) {
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

        if (!$ip || !$password) {
            return [
                'status' => 'failed',
                'message' => 'missing IP or password',
            ];
        }

        if ($isNexus && !$username) {
            return [
                'status' => 'failed',
                'message' => 'missing username for Nexus backup',
            ];
        }

        if (!$isNexus && !$enablePassword) {
            return [
                'status' => 'failed',
                'message' => 'missing enable password for backup',
            ];
        }

        $script = $isNexus ? 'nexus_backup.sh' : '4948_backup.sh';
        $scriptPath = base_path('scripts/' . $script);
        if (!is_file($scriptPath)) {
            return [
                'status' => 'failed',
                'message' => "script not found: {$script}",
            ];
        }

        $command = $isNexus
            ? ['bash', $scriptPath, $ip, $username, $password, $location]
            : ['bash', $scriptPath, $ip, $password, $enablePassword, $location];

        $process = new Process($command, base_path());
        $process->setTimeout(180);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }

        if (!$process->isSuccessful()) {
            $errorOutput = trim($process->getOutput() . "\n" . $process->getErrorOutput());
            return [
                'status' => 'failed',
                'message' => $errorOutput !== '' ? $errorOutput : 'backup script failed',
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
}
