<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Support\ProvisioningTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RunDeviceBackups extends Command
{
    protected $signature = 'devices:run-backups {--device=} {--limit=}';

    protected $description = 'Run config backups for Cisco and OLT devices.';

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
        $olt = data_get($meta, 'olt', []);
        $traceContext = $this->backupTraceContext($device, [
            'trigger' => 'devices:run-backups',
        ]);

        $this->writeProvisioningLog('backup trace: scheduled backup evaluation started', $traceContext);

        $type = strtoupper((string) ($device->type ?? ''));
        $isOlt = $type === 'OLT';
        if ($type !== 'CISCO' && !$isOlt) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - unsupported device type', $traceContext + [
                'device_type' => $type !== '' ? $type : null,
            ]);
            return [
                'status' => 'skipped',
                'message' => 'not a backup-supported device type',
            ];
        }

        if (!$isOlt && (!is_array($cisco) || empty($cisco))) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - invalid Cisco metadata', $traceContext);
            return [
                'status' => 'skipped',
                'message' => 'missing Cisco metadata',
            ];
        }

        if ($isOlt && (!is_array($olt) || empty($olt))) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - invalid OLT metadata', $traceContext);
            return [
                'status' => 'skipped',
                'message' => 'missing OLT metadata',
            ];
        }

        $backupSupportMessage = $this->backupUnsupportedMessage($device, $isOlt, is_array($olt) ? $olt : []);
        if ($backupSupportMessage !== null) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - backup not supported for device', $traceContext + [
                'reason' => $backupSupportMessage,
            ]);

            return [
                'status' => 'skipped',
                'message' => $backupSupportMessage,
            ];
        }

        if (!empty($meta['monitoring_disabled'])) {
            $this->writeProvisioningLog('backup trace: scheduled backup skipped - device deactivated', $traceContext);
            return [
                'status' => 'skipped',
                'message' => 'device is deactivated',
            ];
        }

        $switchModel = strtoupper((string) ($isOlt
            ? $this->firstNonEmpty(
                $olt['model'] ?? null,
                $device->model
            )
            : $this->firstNonEmpty(
                $cisco['switch_model'] ?? null,
                $meta['subtype'] ?? null,
                $device->model
            )));
        $isNexus = !$isOlt && str_contains($switchModel, 'NEXUS');
        $is3560 = !$isOlt && str_contains($switchModel, '3560');

        $ip = $this->firstNonEmpty(
            $isOlt ? ($olt['ip_address'] ?? null) : ($cisco['ip_address'] ?? null),
            $device->ip_address
        );
        $passwordCandidates = $isOlt
            ? $this->normalizeCredentialCandidates([
                $olt['password'] ?? null,
            ])
            : $this->resolveCiscoPasswordCandidates($meta, is_array($cisco) ? $cisco : []);
        $password = $passwordCandidates[0] ?? null;
        $username = $this->firstNonEmpty(
            $isOlt ? ($olt['username'] ?? null) : ($cisco['username'] ?? null),
            $isOlt ? ($olt['user'] ?? null) : ($cisco['user'] ?? null)
        );
        $enablePasswordCandidates = $isOlt
            ? []
            : $this->resolveCiscoEnablePasswordCandidates(
                $meta,
                is_array($cisco) ? $cisco : [],
                $passwordCandidates
            );
        $enablePassword = $enablePasswordCandidates[0] ?? null;
        $location = $this->resolveFolderLocation($device, $cisco, $olt, $isOlt);
        $resolvedBackupDirectory = $this->prepareBackupDirectory($location);
        if (!$resolvedBackupDirectory) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed - backup directory could not be prepared', $traceContext + [
                'switch_model' => $switchModel !== '' ? $switchModel : null,
                'is_nexus' => $isNexus,
                'is_3560' => $is3560,
                'is_olt' => $isOlt,
                'switch_ip' => $ip,
                'location' => $location,
            ]);

            return [
                'status' => 'failed',
                'message' => 'backup folder could not be prepared',
            ];
        }
        $backupSnapshot = $this->snapshotBackupFiles($resolvedBackupDirectory);

        $this->writeProvisioningLog('backup trace: scheduled backup inputs resolved', $traceContext + [
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'is_3560' => $is3560,
            'is_olt' => $isOlt,
            'switch_ip' => $ip,
            'location' => $location,
            'password_present' => $password !== null && $password !== '',
            'enable_password_present' => $enablePassword !== null && $enablePassword !== '',
            'password_candidate_count' => count($passwordCandidates),
            'enable_password_candidate_count' => count($enablePasswordCandidates),
            'username_present' => $username !== null && $username !== '',
        ]);

        if (!$ip || !$password) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing IP or password', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing IP or password',
            ];
        }

        if ($isOlt && !$username) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing OLT username', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing username for OLT backup',
            ];
        }

        if ($isNexus && !$username) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing Nexus username', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing username for Nexus backup',
            ];
        }

        if (!$isOlt && !$isNexus && !$enablePassword) {
            $this->writeProvisioningLog('backup trace: scheduled backup failed validation - missing enable password', $traceContext);
            return [
                'status' => 'failed',
                'message' => 'missing enable password for backup',
            ];
        }

        $script = $isOlt ? 'olt_backup.sh' : ($isNexus ? 'nexus_backup.sh' : ($is3560 ? '3560_backup.sh' : '4948_backup.sh'));
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

        if ($isOlt) {
            $command = ['bash', $scriptPath, $ip, $username, $password, $resolvedBackupDirectory['absolute']];
        } elseif ($isNexus) {
            $command = ['bash', $scriptPath, $ip, $username, $password, $location];
        } elseif ($is3560) {
            $command = ['bash', $scriptPath, $ip, $password, $enablePassword, $location];
            if ($username) {
                $command[] = $username;
            }
        } else {
            $command = ['bash', $scriptPath, $ip, $password, $enablePassword, $location];
        }

        $result = $this->runLoggedProcess($command, $traceContext + [
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'is_3560' => $is3560,
            'is_olt' => $isOlt,
            'switch_ip' => $ip,
            'location' => $location,
            'script_name' => $script,
            'script_path' => $scriptPath,
            'command' => $this->redactBackupCommand($command, $isNexus),
            'secret_values' => array_values(array_filter($isOlt ? [$username, $password] : ($isNexus ? [$password] : [$password, $enablePassword]))),
        ]);

        $result = $this->verifyBackupArtifact($device, $result, $backupSnapshot, $traceContext + [
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'is_3560' => $is3560,
            'is_olt' => $isOlt,
            'switch_ip' => $ip,
            'location' => $location,
            'script_name' => $script,
            'script_path' => $scriptPath,
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

    private function resolveFolderLocation(Device $device, array $cisco, array $olt, bool $isOlt): string
    {
        $location = $isOlt
            ? $this->firstNonEmpty(
                $olt['folder_location'] ?? null,
                $cisco['folder_location'] ?? null,
                data_get($device->metadata, 'folder_location')
            )
            : $this->firstNonEmpty(
                $cisco['folder_location'] ?? null,
                $olt['folder_location'] ?? null,
                data_get($device->metadata, 'folder_location')
            );

        if ($location) {
            return $location;
        }

        $baseName = $this->firstNonEmpty(
            $isOlt ? ($olt['model'] ?? null) : ($cisco['name'] ?? null),
            $device->name,
            'device'
        );
        $slug = preg_replace('/\s+/', '_', trim((string) $baseName));

        return 'uno/' . ($slug !== '' ? $slug : 'device');
    }

    private function snapshotBackupFiles(array $resolvedBackupDirectory): array
    {
        return [
            'relative' => $resolvedBackupDirectory['relative'],
            'absolute' => $resolvedBackupDirectory['absolute'],
            'files' => $this->backupFileMap($resolvedBackupDirectory['absolute']),
        ];
    }

    private function prepareBackupDirectory(string $relative): ?array
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $absolute = null;
        foreach ($this->backupRoots() as $root) {
            $candidate = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($absolute === null) {
                $absolute = $candidate;
            }
            if (is_dir(dirname($candidate)) || is_dir($root)) {
                $absolute = $candidate;
                break;
            }
        }

        $absolute ??= base_path($relative);

        try {
            if (!is_dir($absolute)) {
                File::ensureDirectoryExists($absolute);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return [
            'relative' => $relative,
            'absolute' => $absolute,
        ];
    }

    private function backupRoots(): array
    {
        $roots = [];

        $configuredRoots = trim((string) env('BACKUP_ROOTS', ''));
        if ($configuredRoots !== '') {
            foreach (explode(',', $configuredRoots) as $root) {
                $root = trim($root);
                if ($root !== '') {
                    $roots[] = $root;
                }
            }
        }

        $singleRoot = trim((string) env('BACKUP_ROOT', ''));
        if ($singleRoot !== '') {
            $roots[] = $singleRoot;
        }

        return array_values(array_unique(array_merge($roots, [
            '/srv/tftp',
            '/srv/tftpboot',
            '/var/lib/tftpboot',
            '/var/tftpboot',
            '/tftpboot',
            base_path(),
            '/var/www/html',
        ])));
    }

    private function backupFileMap(string $absolutePath): array
    {
        if (!is_dir($absolutePath)) {
            return [];
        }

        $files = [];
        foreach (File::files($absolutePath) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[$file->getFilename()] = $file->getMTime();
        }

        return $files;
    }

    private function detectCreatedBackupFile(array $beforeFiles, array $afterFiles): array
    {
        $createdFile = null;
        $createdMtime = null;

        foreach ($afterFiles as $name => $mtime) {
            $previousMtime = $beforeFiles[$name] ?? null;
            if ($previousMtime === null || $mtime > $previousMtime) {
                if ($createdMtime === null || $mtime > $createdMtime) {
                    $createdFile = $name;
                    $createdMtime = $mtime;
                }
            }
        }

        return [
            'created_file' => $createdFile,
            'created_mtime' => $createdMtime,
        ];
    }

    private function detectCreatedBackupFileWithRetry(array $beforeFiles, string $absolutePath): array
    {
        $afterFiles = [];
        $createdFile = null;
        $createdMtime = null;
        $maxAttempts = 8;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $afterFiles = $this->backupFileMap($absolutePath);
            $detected = $this->detectCreatedBackupFile($beforeFiles, $afterFiles);
            $createdFile = $detected['created_file'] ?? null;
            $createdMtime = $detected['created_mtime'] ?? null;

            if ($createdFile !== null) {
                break;
            }

            if ($attempt < $maxAttempts) {
                usleep(500000);
            }
        }

        return [
            'created_file' => $createdFile,
            'created_mtime' => $createdMtime,
            'after_files' => $afterFiles,
        ];
    }

    private function detectBackupFileFromProcessOutput(string $output, string $absolutePath): ?array
    {
        $output = trim($output);
        if ($output === '' || !preg_match('/(bytes copied|copied in|copy complete|copied successfully)/i', $output)) {
            return null;
        }

        $searchDirectories = [];
        if (is_dir($absolutePath)) {
            $searchDirectories[] = $absolutePath;
        }
        foreach ($this->backupRoots() as $root) {
            if (is_dir($root)) {
                $searchDirectories[] = $root;
            }
        }
        $searchDirectories = array_values(array_unique($searchDirectories));

        $candidateNames = [];
        if (preg_match_all('/([0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.[A-Za-z0-9]+)/', $output, $matches)) {
            foreach ($matches[1] as $match) {
                $candidateNames[] = basename(str_replace('\\', '/', (string) $match));
            }
        }

        $candidateNames = array_values(array_unique(array_filter($candidateNames, static fn ($name): bool => $name !== '')));
        foreach ($candidateNames as $candidateName) {
            foreach ($searchDirectories as $directory) {
                $candidatePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidateName;
                if (is_file($candidatePath)) {
                    $mtime = filemtime($candidatePath);

                    return [
                        'file' => $candidateName,
                        'mtime' => is_int($mtime) ? $mtime : time(),
                        'path' => $candidatePath,
                    ];
                }
            }
        }

        $latestFile = null;
        $latestMtime = null;
        $latestPath = null;
        foreach ($searchDirectories as $directory) {
            $afterFiles = $this->backupFileMap($directory);
            foreach ($afterFiles as $name => $mtime) {
                if ($latestMtime === null || $mtime > $latestMtime) {
                    $latestFile = $name;
                    $latestMtime = $mtime;
                    $latestPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                }
            }
        }

        if ($latestFile !== null && $latestMtime !== null && $latestMtime >= (time() - 300)) {
            return [
                'file' => $latestFile,
                'mtime' => $latestMtime,
                'path' => $latestPath,
            ];
        }

        return null;
    }

    private function verifyBackupArtifact(Device $device, array $processResult, array $snapshot, array $traceContext): array
    {
        if (!($processResult['ok'] ?? false)) {
            return $processResult;
        }

        $beforeFiles = $snapshot['files'] ?? [];
        $detected = $this->detectCreatedBackupFileWithRetry($beforeFiles, $snapshot['absolute']);
        $afterFiles = $detected['after_files'] ?? [];
        $createdFile = $detected['created_file'] ?? null;
        $createdMtime = $detected['created_mtime'] ?? null;

        if ($createdFile !== null) {
            $this->writeProvisioningLog('backup trace: verification succeeded - backup file detected', $this->withoutProvisioningSecrets($traceContext) + [
                'backup_file' => $createdFile,
                'backup_modified_at' => date('Y-m-d H:i:s', $createdMtime),
                'backup_folder' => $snapshot['relative'],
            ]);

            return $processResult;
        }

        $outputDetected = $this->detectBackupFileFromProcessOutput(
            (string) ($processResult['output'] ?? ''),
            $snapshot['absolute']
        );
        if ($outputDetected !== null) {
            $outputDetected = $this->moveDetectedBackupIntoDirectory($outputDetected, $snapshot['absolute']);
            $this->writeProvisioningLog('backup trace: verification recovered from process output', $this->withoutProvisioningSecrets($traceContext) + [
                'backup_file' => $outputDetected['file'],
                'backup_modified_at' => date('Y-m-d H:i:s', (int) $outputDetected['mtime']),
                'backup_folder' => $snapshot['relative'],
                'backup_path' => $outputDetected['path'] ?? null,
            ]);

            return $processResult;
        }

        $this->writeProvisioningLog('backup trace: verification failed - no new backup file detected', $this->withoutProvisioningSecrets($traceContext) + [
            'backup_folder' => $snapshot['relative'],
            'backup_path' => $snapshot['absolute'],
            'existing_file_count_before' => count($beforeFiles),
            'existing_file_count_after' => count($afterFiles),
        ]);

        return [
            'ok' => false,
            'output' => trim(($processResult['output'] ?? '') . "\nBackup verification failed: no new backup file was created in {$snapshot['relative']}."),
        ];
    }

    private function moveDetectedBackupIntoDirectory(array $detected, string $targetDirectory): array
    {
        $fileName = basename((string) ($detected['file'] ?? ''));
        if ($fileName === '' || !is_dir($targetDirectory)) {
            return $detected;
        }

        $sourcePath = (string) ($detected['path'] ?? '');
        if ($sourcePath === '' || !is_file($sourcePath)) {
            foreach ($this->backupRoots() as $root) {
                $candidatePath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($candidatePath)) {
                    $sourcePath = $candidatePath;
                    break;
                }
            }
        }

        if ($sourcePath === '' || !is_file($sourcePath)) {
            return $detected;
        }

        $targetPath = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $sourceReal = realpath($sourcePath) ?: $sourcePath;
        $targetDirReal = realpath($targetDirectory) ?: $targetDirectory;
        if ($sourceReal === $targetPath || str_starts_with($sourceReal, rtrim($targetDirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $mtime = filemtime($sourcePath);
            $detected['path'] = $sourcePath;
            $detected['mtime'] = is_int($mtime) ? $mtime : (int) ($detected['mtime'] ?? time());
            $detected['file'] = $fileName;
            return $detected;
        }

        try {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            $moved = @rename($sourcePath, $targetPath);
            if (!$moved) {
                $moved = @copy($sourcePath, $targetPath);
                if ($moved) {
                    @unlink($sourcePath);
                }
            }

            if ($moved && is_file($targetPath)) {
                $mtime = filemtime($targetPath);
                $detected['path'] = $targetPath;
                $detected['mtime'] = is_int($mtime) ? $mtime : (int) ($detected['mtime'] ?? time());
                $detected['file'] = $fileName;
            }
        } catch (\Throwable) {
            return $detected;
        }

        return $detected;
    }

    private function backupUnsupportedMessage(Device $device, bool $isOlt, array $olt = []): ?string
    {
        if (!$isOlt) {
            return null;
        }

        $oltType = strtoupper((string) $this->firstNonEmpty(data_get($olt, 'device_type'), ''));
        if ($oltType === 'HUAWEI') {
            return null;
        }

        if ($oltType === '') {
            $model = strtoupper((string) $this->firstNonEmpty(data_get($olt, 'model'), $device->model, ''));
            if (str_contains($model, 'HUAWEI')) {
                return null;
            }
        }

        $typeLabel = $oltType !== '' ? ucfirst(strtolower($oltType)) : 'non-Huawei';
        return "Backup is supported only for Huawei OLT devices. Current OLT type: {$typeLabel}.";
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

    private function normalizeCredentialCandidates(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $candidate = $this->decryptValue(is_scalar($value) ? (string) $value : null);
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if (!in_array($candidate, $normalized, true)) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
    }

    private function resolveCiscoPasswordCandidates(array $meta, array $cisco): array
    {
        $ciscoPaths = [
            'password',
            'credentials.password',
            'login_password',
            'telnet_password',
            'cisco_password',
            'passwd',
            'pass',
            'device_password',
        ];
        $metaPaths = [
            'password',
            'credentials.password',
            'login_password',
            'telnet_password',
            'cisco_password',
            'passwd',
            'pass',
            'device_password',
            'cisco.password',
            'cisco.credentials.password',
            'cisco.login_password',
            'cisco.telnet_password',
            'cisco.cisco_password',
            'cisco.passwd',
            'cisco.pass',
            'cisco.device_password',
        ];

        return $this->normalizeCredentialCandidates(array_merge(
            $this->extractCredentialValues($cisco, $ciscoPaths),
            $this->extractCredentialValues($meta, $metaPaths)
        ));
    }

    private function resolveCiscoEnablePasswordCandidates(array $meta, array $cisco, array $passwordCandidates = []): array
    {
        $ciscoPaths = [
            'enable_password',
            'credentials.enable_password',
            'enable_secret',
            'enable',
            'ena',
            'secret',
            'enable_pass',
            'enablepass',
            'enable_pwd',
            'privileged_password',
            'privilege_password',
            'super_password',
            'enablePassword',
            'enableSecret',
        ];
        $metaPaths = [
            'enable_password',
            'credentials.enable_password',
            'enable_secret',
            'enable',
            'ena',
            'secret',
            'enable_pass',
            'enablepass',
            'enable_pwd',
            'privileged_password',
            'privilege_password',
            'super_password',
            'enablePassword',
            'enableSecret',
            'cisco.enable_password',
            'cisco.credentials.enable_password',
            'cisco.enable_secret',
            'cisco.enable',
            'cisco.ena',
            'cisco.secret',
            'cisco.enable_pass',
            'cisco.enablepass',
            'cisco.enable_pwd',
            'cisco.privileged_password',
            'cisco.privilege_password',
            'cisco.super_password',
            'cisco.enablePassword',
            'cisco.enableSecret',
        ];

        return $this->normalizeCredentialCandidates(array_merge(
            $this->extractCredentialValues($cisco, $ciscoPaths),
            $this->extractCredentialValues($meta, $metaPaths),
            $passwordCandidates
        ));
    }

    private function extractCredentialValues(array $source, array $paths): array
    {
        $values = [];
        foreach ($paths as $path) {
            $values[] = data_get($source, $path);
        }

        return $values;
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
        $process->setEnv(array_merge($_ENV, $_SERVER, ProvisioningTrace::childProcessEnv()));
        $secretValues = array_values(array_filter($traceContext['secret_values'] ?? []));
        $traceResult = ProvisioningTrace::runProcess($process, [
            'label' => 'backup trace',
            'line_prefix' => 'backup trace',
            'context' => $this->withoutProvisioningSecrets($traceContext + [
                'layer' => $traceContext['layer'] ?? 'process_execution',
                'protocol' => $traceContext['protocol'] ?? 'TELNET',
                'device_ip' => $traceContext['device_ip'] ?? $traceContext['switch_ip'] ?? null,
            ]),
            'secret_values' => $secretValues,
            'log_output' => true,
        ]);

        return [
            'ok' => (bool) ($traceResult['ok'] ?? false),
            'output' => (string) ($traceResult['output'] ?? ''),
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
