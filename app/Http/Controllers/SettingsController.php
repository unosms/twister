<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplySystemTimezone;
use App\Models\Alert;
use App\Models\AppSetting;
use App\Models\Device;
use App\Models\SystemNotification;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Support\BackupSchedule;
use App\Support\ProvisioningTrace;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;

class SettingsController extends Controller
{
    private const TELEGRAM_TEMPLATE_SETTINGS = [
        'default' => 'telegram_template_default',
        'low' => 'telegram_template_low',
        'medium' => 'telegram_template_medium',
        'high' => 'telegram_template_high',
        'critical' => 'telegram_template_critical',
    ];

    private const TELEGRAM_EVENT_TYPES_CUSTOM_SETTING = 'telegram_event_types_custom';

    public function index(Request $request)
    {
        $settingsReady = AppSetting::supportsStorage();
        $currentTimezone = $settingsReady
            ? (string) AppSetting::getValue('timezone', config('app.timezone', 'UTC'))
            : (string) config('app.timezone', 'UTC');

        $currentTimezone = in_array($currentTimezone, timezone_identifiers_list(), true)
            ? $currentTimezone
            : (string) config('app.timezone', 'UTC');

        $backupDevices = Device::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'model', 'metadata'])
            ->map(function (Device $device): array {
                $resolved = $this->resolveBackupDirectory($device);

                return [
                    'id' => $device->id,
                    'name' => (string) $device->name,
                    'type' => (string) ($device->type ?? ''),
                    'model' => (string) ($device->model ?? ''),
                    'folder' => $resolved['relative'] ?? null,
                ];
            })
            ->values()
            ->all();
        $backupScripts = $this->backupScriptInventory();
        $telegramSettings = $this->telegramTemplateSettings();

        return view('settings_system', [
            'currentTimezone' => $currentTimezone,
            'timezoneEntries' => $this->buildTimezoneEntries(),
            'countryOptions' => $this->buildCountryOptions(),
            'settingsReady' => $settingsReady,
            'backupScheduleIntervalHours' => BackupSchedule::intervalHours(),
            'backupScheduleOptions' => BackupSchedule::allowedIntervals(),
            'backupScheduleLabel' => BackupSchedule::humanLabel(),
            'backupDevices' => $backupDevices,
            'backupRootOptions' => $this->backupRoots(),
            'backupTftpServerAddress' => $this->backupTftpServerAddress(),
            'backupScripts' => $backupScripts,
            'backupScriptsRoot' => str_replace('\\', '/', base_path('scripts')),
            'maintenanceStats' => [
                'device_count' => Device::count(),
                'backup_device_count' => count($backupDevices),
                'backup_script_count' => count($backupScripts),
                'alert_count' => $this->tableCount('alerts'),
                'notification_count' => $this->tableCount('notifications'),
                'telemetry_count' => $this->tableCount('telemetry_logs'),
                'interface_event_count' => $this->tableCount('interface_events'),
                'device_event_count' => $this->tableCount('device_events'),
            ],
            'cleanupAutoSettings' => [
                'logs' => $this->cleanupAutoSettingFor('logs'),
                'notifications' => $this->cleanupAutoSettingFor('notifications'),
                'events' => $this->cleanupAutoSettingFor('events'),
            ],
            'telegramEventTypesCustom' => $telegramSettings['event_types_custom'],
            'telegramTemplateDefault' => $telegramSettings['default'],
            'telegramTemplateLow' => $telegramSettings['low'],
            'telegramTemplateMedium' => $telegramSettings['medium'],
            'telegramTemplateHigh' => $telegramSettings['high'],
            'telegramTemplateCritical' => $telegramSettings['critical'],
            'configBackupTableLabels' => $this->configurationBackupTableLabels(),
            'selectedBackupDeviceId' => old('device_id'),
            'importRequiresConfirmation' => true,
        ]);
    }

    public function update(Request $request)
    {
        if (!AppSetting::supportsStorage()) {
            return back()->withErrors([
                'settings' => 'The settings table is missing the required key/value columns. Run php artisan migrate first.',
            ]);
        }

        $timezoneOptions = timezone_identifiers_list();

        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in($timezoneOptions)],
        ]);

        AppSetting::putValue('timezone', $data['timezone']);
        ApplySystemTimezone::flushCache();

        config(['app.timezone' => $data['timezone']]);
        date_default_timezone_set($data['timezone']);

        return redirect()
            ->route('settings.index')
            ->with('status', "System timezone updated to {$data['timezone']}.");
    }

    public function updateTelegramTemplates(Request $request)
    {
        if (!AppSetting::supportsStorage()) {
            return back()->withErrors([
                'telegram_settings' => 'The settings table is missing the required key/value columns. Run php artisan migrate first.',
            ]);
        }

        $data = $request->validate([
            'telegram_event_types_custom' => ['nullable', 'string', 'max:500'],
            'telegram_template_default' => ['nullable', 'string', 'max:4000'],
            'telegram_template_low' => ['nullable', 'string', 'max:4000'],
            'telegram_template_medium' => ['nullable', 'string', 'max:4000'],
            'telegram_template_high' => ['nullable', 'string', 'max:4000'],
            'telegram_template_critical' => ['nullable', 'string', 'max:4000'],
        ]);

        $customTypes = $this->normalizeTelegramEventTypesCustom(
            (string) ($data['telegram_event_types_custom'] ?? '')
        );
        AppSetting::putValue(self::TELEGRAM_EVENT_TYPES_CUSTOM_SETTING, $customTypes !== '' ? $customTypes : null);

        foreach (self::TELEGRAM_TEMPLATE_SETTINGS as $fieldName => $settingKey) {
            $value = trim((string) ($data[$settingKey] ?? ''));
            AppSetting::putValue($settingKey, $value !== '' ? $value : null);
        }

        return redirect()
            ->route('settings.index')
            ->with('status', 'Telegram template settings updated.');
    }

    public function manageBackups(Request $request)
    {
        $data = $request->validate([
            'operation' => ['required', 'string', Rule::in(['run_all', 'clear_all', 'clear_device'])],
            'device_id' => [
                Rule::requiredIf(static fn () => $request->input('operation') === 'clear_device'),
                'nullable',
                'integer',
                'exists:devices,id',
            ],
        ]);

        return match ($data['operation']) {
            'run_all' => $this->runAllBackups(),
            'clear_all' => $this->clearBackupsForAllDevices(),
            'clear_device' => $this->clearBackupsForSingleDevice((int) $data['device_id']),
        };
    }

    public function updateBackupSchedule(Request $request)
    {
        if (!AppSetting::supportsStorage()) {
            return back()->withErrors([
                'backup_schedule' => 'The settings table is missing the required key/value columns. Run php artisan migrate first.',
            ]);
        }

        $allowedIntervals = BackupSchedule::allowedIntervals();

        $data = $request->validate([
            'backup_schedule_interval_hours' => ['required', 'integer', Rule::in($allowedIntervals)],
        ]);

        $hours = (int) $data['backup_schedule_interval_hours'];
        AppSetting::putValue('backup_schedule_interval_hours', $hours);

        return redirect()
            ->route('settings.index')
            ->with('status', 'Backup scheduler updated to ' . BackupSchedule::humanLabel($hours) . '.');
    }

    public function backupDatabaseToFtp(Request $request)
    {
        $data = $request->validate([
            'backup_server_type' => ['required', 'string', Rule::in(['standalone_server', 'virtual_server'])],
            'database_name' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'ftp_server_ip' => ['required', 'string', 'max:255'],
            'ftp_username' => ['required', 'string', 'max:255'],
            'ftp_password' => ['required', 'string', 'max:255'],
        ]);

        $defaultConnection = (string) config('database.default', 'mysql');
        $connection = config("database.connections.{$defaultConnection}", []);
        $driver = strtolower((string) data_get($connection, 'driver', ''));

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return back()->withErrors([
                'database_backup' => 'Database backup currently supports MySQL/MariaDB connections only.',
            ])->withInput();
        }

        $dbHost = (string) data_get($connection, 'host', '127.0.0.1');
        $dbPort = (string) data_get($connection, 'port', '3306');
        $dbUser = (string) data_get($connection, 'username', '');
        $dbPassword = (string) data_get($connection, 'password', '');

        if ($dbUser === '') {
            return back()->withErrors([
                'database_backup' => 'Database username is missing in server configuration.',
            ])->withInput();
        }

        $serverType = (string) $data['backup_server_type'];
        $databaseName = (string) $data['database_name'];
        $ftpHost = (string) $data['ftp_server_ip'];
        $ftpUsername = (string) $data['ftp_username'];
        $ftpPassword = (string) $data['ftp_password'];

        $safeServerType = str_replace(['standalone_server', 'virtual_server'], ['standalone', 'virtual'], $serverType);
        $safeDatabaseName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $databaseName) ?: 'database';
        $fileName = "{$safeServerType}-{$safeDatabaseName}-" . now()->format('Y-m-d_H-i-s') . '.sql';
        $dumpDirectory = storage_path('app/database-backups');
        $dumpPath = $dumpDirectory . DIRECTORY_SEPARATOR . $fileName;

        try {
            File::ensureDirectoryExists($dumpDirectory);

            $dumpCommand = [
                $this->mysqldumpBinary(),
                '--host=' . $dbHost,
                '--port=' . $dbPort,
                '--user=' . $dbUser,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--set-gtid-purged=OFF',
                '--databases',
                $databaseName,
                '--result-file=' . $dumpPath,
            ];

            $process = new Process($dumpCommand, base_path());
            $process->setTimeout(300);
            $process->setEnv(array_merge($_ENV, $_SERVER, [
                'MYSQL_PWD' => $dbPassword,
            ]));
            $process->run();

            if (!$process->isSuccessful()) {
                $error = trim($process->getErrorOutput());
                $output = trim($process->getOutput());
                throw new \RuntimeException($error !== '' ? $error : ($output !== '' ? $output : 'mysqldump failed.'));
            }

            if (!is_file($dumpPath) || (int) filesize($dumpPath) <= 0) {
                throw new \RuntimeException('Backup dump file was not created.');
            }

            $this->uploadBackupFileToFtp($dumpPath, $fileName, $ftpHost, $ftpUsername, $ftpPassword);
            File::delete($dumpPath);

            return redirect()
                ->route('settings.index')
                ->with('status', "Database backup uploaded successfully: {$fileName}");
        } catch (\Throwable $exception) {
            if (is_file($dumpPath)) {
                File::delete($dumpPath);
            }

            return back()->withErrors([
                'database_backup' => 'Could not backup database to FTP: ' . $exception->getMessage(),
            ])->withInput();
        }
    }

    public function clearLogs(Request $request)
    {
        $data = $request->validate([
            'operation' => ['nullable', 'string', Rule::in(['clear_all', 'clear_range', 'set_auto'])],
            'range_start' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'clear_range'),
                'nullable',
                'date',
            ],
            'range_end' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'clear_range'),
                'nullable',
                'date',
            ],
            'auto_cleanup_enabled' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'set_auto'),
                'nullable',
                'integer',
                Rule::in([0, 1]),
            ],
            'auto_cleanup_frequency' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'set_auto'),
                'nullable',
                'string',
                Rule::in(['weekly', 'monthly', 'yearly']),
            ],
        ]);

        $operation = (string) ($data['operation'] ?? 'clear_all');

        try {
            if ($operation === 'set_auto') {
                if (!AppSetting::supportsStorage()) {
                    return back()->withErrors([
                        'logs' => 'The settings table is not available for auto-cleanup preferences.',
                    ])->withInput();
                }

                $enabled = (int) ($data['auto_cleanup_enabled'] ?? 0) === 1;
                $frequency = strtolower(trim((string) ($data['auto_cleanup_frequency'] ?? 'weekly')));
                $this->saveCleanupAutoSetting('logs', $enabled, $frequency);

                return redirect()
                    ->route('settings.index')
                    ->with('status', 'Logs auto-cleanup ' . ($enabled ? 'enabled' : 'disabled') . " ({$frequency}).");
            }

            if ($operation === 'clear_range') {
                [$rangeStartAt, $rangeEndAt] = $this->parseCleanupRange(
                    (string) ($data['range_start'] ?? ''),
                    (string) ($data['range_end'] ?? '')
                );

                $telemetryDeleted = 0;
                if (Schema::hasTable('telemetry_logs')) {
                    $telemetryQuery = TelemetryLog::query();
                    $this->applyDateRangeToQuery($telemetryQuery, 'recorded_at', 'created_at', $rangeStartAt, $rangeEndAt);
                    $telemetryDeleted = $telemetryQuery->delete();
                }

                return redirect()
                    ->route('settings.index')
                    ->with('status', "Cleared {$telemetryDeleted} telemetry log row(s) in the selected date range.");
            }

            $telemetryDeleted = Schema::hasTable('telemetry_logs')
                ? TelemetryLog::query()->delete()
                : 0;

            $logPaths = [];
            if (File::isDirectory(storage_path('logs'))) {
                foreach (File::files(storage_path('logs')) as $file) {
                    if ($file->getFilename() === '.gitignore') {
                        continue;
                    }

                    $logPaths[] = $file->getPathname();
                }
            }

            $logPaths[] = ProvisioningTrace::rawLogPath();
            $logPaths[] = ProvisioningTrace::eventLogPath();
            $logPaths = array_values(array_unique(array_filter($logPaths, static fn ($path): bool => is_string($path) && $path !== '')));

            $clearedFiles = 0;
            foreach ($logPaths as $path) {
                try {
                    $directory = dirname($path);
                    if (!File::isDirectory($directory)) {
                        File::ensureDirectoryExists($directory);
                    }
                    File::put($path, '');
                    $clearedFiles++;
                } catch (\Throwable) {
                    // Ignore individual file reset failures and keep clearing the remaining logs.
                }
            }

            return redirect()
                ->route('settings.index')
                ->with('status', "Cleared {$telemetryDeleted} telemetry row(s) and reset {$clearedFiles} log file(s).");
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'logs' => 'Could not clear logs: ' . $exception->getMessage(),
            ])->withInput();
        }
    }

    public function clearNotifications(Request $request)
    {
        $data = $request->validate([
            'operation' => ['nullable', 'string', Rule::in(['clear_all', 'clear_range', 'set_auto'])],
            'range_start' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'clear_range'),
                'nullable',
                'date',
            ],
            'range_end' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'clear_range'),
                'nullable',
                'date',
            ],
            'auto_cleanup_enabled' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'set_auto'),
                'nullable',
                'integer',
                Rule::in([0, 1]),
            ],
            'auto_cleanup_frequency' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'set_auto'),
                'nullable',
                'string',
                Rule::in(['weekly', 'monthly', 'yearly']),
            ],
        ]);

        $operation = (string) ($data['operation'] ?? 'clear_all');

        try {
            if ($operation === 'set_auto') {
                if (!AppSetting::supportsStorage()) {
                    return back()->withErrors([
                        'notifications' => 'The settings table is not available for auto-cleanup preferences.',
                    ])->withInput();
                }

                $enabled = (int) ($data['auto_cleanup_enabled'] ?? 0) === 1;
                $frequency = strtolower(trim((string) ($data['auto_cleanup_frequency'] ?? 'weekly')));
                $this->saveCleanupAutoSetting('notifications', $enabled, $frequency);

                return redirect()
                    ->route('settings.index')
                    ->with('status', 'Notifications auto-cleanup ' . ($enabled ? 'enabled' : 'disabled') . " ({$frequency}).");
            }

            if ($operation === 'clear_range') {
                [$rangeStartAt, $rangeEndAt] = $this->parseCleanupRange(
                    (string) ($data['range_start'] ?? ''),
                    (string) ($data['range_end'] ?? '')
                );

                $notificationDeleted = 0;
                $alertDeleted = 0;
                DB::transaction(function () use (&$notificationDeleted, &$alertDeleted, $rangeStartAt, $rangeEndAt): void {
                    if (Schema::hasTable('notifications')) {
                        $notificationQuery = SystemNotification::query();
                        $this->applyDateRangeToQuery($notificationQuery, 'created_at', 'updated_at', $rangeStartAt, $rangeEndAt);
                        $notificationDeleted = $notificationQuery->delete();
                    }

                    if (Schema::hasTable('alerts')) {
                        $alertQuery = Alert::query();
                        $this->applyDateRangeToQuery($alertQuery, 'created_at', 'updated_at', $rangeStartAt, $rangeEndAt);
                        $alertDeleted = $alertQuery->delete();
                    }
                });

                return redirect()
                    ->route('settings.index')
                    ->with('status', "Cleared {$alertDeleted} alert(s) and {$notificationDeleted} notification(s) in the selected date range.");
            }

            $notificationCount = $this->tableCount('notifications');
            $alertCount = $this->tableCount('alerts');

            DB::transaction(function (): void {
                if (Schema::hasTable('notifications')) {
                    SystemNotification::query()->delete();
                }

                if (Schema::hasTable('alerts')) {
                    Alert::query()->delete();
                }
            });

            return redirect()
                ->route('settings.index')
                ->with('status', "Cleared {$alertCount} alert(s) and {$notificationCount} notification record(s).");
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'notifications' => 'Could not clear notifications: ' . $exception->getMessage(),
            ])->withInput();
        }
    }

    public function manageEvents(Request $request)
    {
        $data = $request->validate([
            'operation' => ['nullable', 'string', Rule::in(['clear_all', 'clear_device', 'clear_range', 'clear_device_range', 'set_auto'])],
            'event_device_id' => [
                Rule::requiredIf(static fn (): bool => in_array(
                    (string) $request->input('operation'),
                    ['clear_device', 'clear_device_range'],
                    true
                )),
                'nullable',
                'integer',
                'exists:devices,id',
            ],
            'range_start' => [
                Rule::requiredIf(static fn (): bool => in_array(
                    (string) $request->input('operation'),
                    ['clear_range', 'clear_device_range'],
                    true
                )),
                'nullable',
                'date',
            ],
            'range_end' => [
                Rule::requiredIf(static fn (): bool => in_array(
                    (string) $request->input('operation'),
                    ['clear_range', 'clear_device_range'],
                    true
                )),
                'nullable',
                'date',
            ],
            'auto_cleanup_enabled' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'set_auto'),
                'nullable',
                'integer',
                Rule::in([0, 1]),
            ],
            'auto_cleanup_frequency' => [
                Rule::requiredIf(static fn (): bool => $request->input('operation') === 'set_auto'),
                'nullable',
                'string',
                Rule::in(['weekly', 'monthly', 'yearly']),
            ],
        ]);

        try {
            $operation = (string) $data['operation'];

            if ($operation === 'set_auto') {
                if (!AppSetting::supportsStorage()) {
                    return back()->withErrors([
                        'events' => 'The settings table is not available for auto-cleanup preferences.',
                    ])->withInput();
                }

                $enabled = (int) ($data['auto_cleanup_enabled'] ?? 0) === 1;
                $frequency = strtolower(trim((string) ($data['auto_cleanup_frequency'] ?? 'weekly')));
                $this->saveCleanupAutoSetting('events', $enabled, $frequency);

                return redirect()
                    ->route('settings.index')
                    ->with('status', 'Events auto-cleanup ' . ($enabled ? 'enabled' : 'disabled') . " ({$frequency}).");
            }

            $hasInterfaceEventsTable = Schema::hasTable('interface_events');
            $hasDeviceEventsTable = Schema::hasTable('device_events');

            if (!$hasInterfaceEventsTable && !$hasDeviceEventsTable) {
                return back()->withErrors([
                    'events' => 'Event tables are not available yet. Run the event poller to generate event history.',
                ]);
            }

            $targetDeviceId = in_array($operation, ['clear_device', 'clear_device_range'], true)
                ? (isset($data['event_device_id']) ? (int) $data['event_device_id'] : null)
                : null;
            $targetDevice = $targetDeviceId
                ? Device::query()->find($targetDeviceId)
                : null;
            $rangeStartTimestamp = null;
            $rangeEndTimestamp = null;

            if (in_array($operation, ['clear_range', 'clear_device_range'], true)) {
                [$rangeStartAt, $rangeEndAt] = $this->parseCleanupRange(
                    (string) ($data['range_start'] ?? ''),
                    (string) ($data['range_end'] ?? '')
                );
                $rangeStartTimestamp = $rangeStartAt->timestamp;
                $rangeEndTimestamp = $rangeEndAt->timestamp;
            }

            $interfaceDeleted = 0;
            $deviceDeleted = 0;

            DB::transaction(function () use (
                $operation,
                $targetDeviceId,
                $rangeStartTimestamp,
                $rangeEndTimestamp,
                $hasInterfaceEventsTable,
                $hasDeviceEventsTable,
                &$interfaceDeleted,
                &$deviceDeleted
            ): void {
                if ($hasInterfaceEventsTable) {
                    $interfaceQuery = DB::table('interface_events');
                    if (in_array($operation, ['clear_device', 'clear_device_range'], true) && $targetDeviceId) {
                        $interfaceQuery->where('device_id', $targetDeviceId);
                    }
                    if (in_array($operation, ['clear_range', 'clear_device_range'], true)) {
                        $this->applyEventTimestampRangeToQuery($interfaceQuery, $rangeStartTimestamp, $rangeEndTimestamp);
                    }
                    $interfaceDeleted = $interfaceQuery->delete();
                }

                if ($hasDeviceEventsTable) {
                    $deviceQuery = DB::table('device_events');
                    if (in_array($operation, ['clear_device', 'clear_device_range'], true) && $targetDeviceId) {
                        $deviceQuery->where('device_id', $targetDeviceId);
                    }
                    if (in_array($operation, ['clear_range', 'clear_device_range'], true)) {
                        $this->applyEventTimestampRangeToQuery($deviceQuery, $rangeStartTimestamp, $rangeEndTimestamp);
                    }
                    $deviceDeleted = $deviceQuery->delete();
                }
            });

            $deletedTotal = $interfaceDeleted + $deviceDeleted;
            if (in_array($operation, ['clear_device', 'clear_device_range'], true) && $targetDeviceId) {
                $deviceLabel = $targetDevice?->name ?: ('Device #' . $targetDeviceId);
                $messagePrefix = $operation === 'clear_device_range'
                    ? 'Cleared date-range events'
                    : 'Cleared';

                return redirect()
                    ->route('settings.index')
                    ->with('status', "{$messagePrefix} {$deletedTotal} event row(s) for {$deviceLabel}.");
            }

            if ($operation === 'clear_range') {
                return redirect()
                    ->route('settings.index')
                    ->with('status', "Cleared {$deletedTotal} event row(s) in the selected date range.");
            }

            return redirect()
                ->route('settings.index')
                ->with('status', "Cleared {$deletedTotal} total event row(s) across all devices.");
        } catch (\Throwable $exception) {
            return back()
                ->withErrors([
                    'events' => 'Could not clear events: ' . $exception->getMessage(),
                ])
                ->withInput();
        }
    }

    public function exportSystemConfiguration(Request $request)
    {
        $actor = User::find($request->session()->get('auth.user_id'));
        $payload = [
            'type' => 'device-control-system-config',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'exported_by' => [
                'user_id' => $actor?->id,
                'name' => $actor?->name,
                'email' => $actor?->email,
            ],
            'tables' => $this->configurationBackupTableLabels(),
            'datasets' => $this->configurationBackupDatasets(),
        ];

        $fileName = 'system-configuration-backup-' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function importSystemConfiguration(Request $request)
    {
        $data = $request->validate([
            'configuration_backup' => ['required', 'file', 'max:20480'],
            'replace_existing' => ['required', 'accepted'],
        ]);

        try {
            $payload = $this->decodeConfigurationBackup($data['configuration_backup']);
            $datasets = $payload['datasets'] ?? null;

            if (!is_array($datasets)) {
                throw new \RuntimeException('The uploaded file does not contain any configuration datasets.');
            }

            $imported = $this->importConfigurationDatasets($datasets);
            ApplySystemTimezone::flushCache();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('auth.login')
                ->with('status', 'System configuration imported successfully. Imported ' . $imported . '. Please sign in again.');
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'configuration_backup' => 'Could not import configuration backup: ' . $exception->getMessage(),
            ]);
        }
    }

    public function exportScriptsBackup(Request $request)
    {
        try {
            $scriptsRoot = base_path('scripts');
            if (!File::isDirectory($scriptsRoot)) {
                throw new \RuntimeException('Scripts directory was not found: ' . $scriptsRoot);
            }

            $scriptFiles = collect(File::allFiles($scriptsRoot))
                ->filter(static fn (\SplFileInfo $file): bool => $file->isFile())
                ->values();

            if ($scriptFiles->isEmpty()) {
                throw new \RuntimeException('No script files were found in the scripts directory.');
            }

            $exportDirectory = storage_path('app/exports/scripts');
            File::ensureDirectoryExists($exportDirectory);

            $archiveFileName = 'system-scripts-backup-' . now()->format('Y-m-d_H-i-s') . '.zip';
            $archivePath = $exportDirectory . DIRECTORY_SEPARATOR . $archiveFileName;
            $manifestPayload = [
                'type' => 'device-control-scripts-backup',
                'generated_at' => now()->toIso8601String(),
                'generated_by_user_id' => $request->session()->get('auth.user_id'),
                'source_root' => str_replace('\\', '/', $scriptsRoot),
                'files_count' => $scriptFiles->count(),
            ];

            if ($this->commandBinaryExists('zip')) {
                $this->createScriptsArchiveWithZipCommand($scriptsRoot, $archivePath, $manifestPayload);
            } elseif (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                $openResult = $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                if ($openResult !== true) {
                    throw new \RuntimeException('Could not create zip archive (code: ' . $openResult . ').');
                }

                foreach ($scriptFiles as $scriptFile) {
                    $absolutePath = $scriptFile->getPathname();
                    $relativePath = ltrim(Str::after($absolutePath, $scriptsRoot), '\\/');
                    $relativePath = str_replace('\\', '/', $relativePath);
                    if ($relativePath === '') {
                        continue;
                    }

                    $zip->addFile($absolutePath, 'scripts/' . $relativePath);
                }

                $zip->addFromString('manifest.json', json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $zip->close();
            } else {
                throw new \RuntimeException('Neither zip command nor ZipArchive is available on this server.');
            }

            return response()->download($archivePath, $archiveFileName)->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('settings.index')
                ->withErrors([
                    'scripts_backup' => 'Could not create scripts backup: ' . $exception->getMessage() . ' (Install zip/unzip and php-zip on server if needed.)',
                ]);
        }
    }

    public function importScriptsBackup(Request $request)
    {
        $data = $request->validate([
            'scripts_backup_archive' => ['required', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed,multipart/x-zip', 'max:102400'],
            'replace_existing_scripts' => ['required', 'accepted'],
        ]);

        $temporaryImportRoot = null;
        $zip = null;

        try {
            $scriptsRoot = base_path('scripts');
            File::ensureDirectoryExists($scriptsRoot);

            $archivePath = $data['scripts_backup_archive']->getRealPath();
            if (!is_string($archivePath) || $archivePath === '') {
                throw new \RuntimeException('Could not access the uploaded scripts archive.');
            }

            $temporaryImportRoot = storage_path('app/imports/scripts/' . (string) Str::uuid());
            $extractRoot = $temporaryImportRoot . DIRECTORY_SEPARATOR . 'extracted';
            File::ensureDirectoryExists($extractRoot);

            if ($this->commandBinaryExists('unzip')) {
                $this->extractScriptsArchiveWithUnzipCommand($archivePath, $extractRoot);
            } elseif (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                $openResult = $zip->open($archivePath);
                if ($openResult !== true) {
                    throw new \RuntimeException('Could not open scripts archive (code: ' . $openResult . ').');
                }

                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entryName = $zip->getNameIndex($index);
                    if (!is_string($entryName) || $entryName === '') {
                        continue;
                    }

                    $normalizedPath = $this->normalizeScriptsZipEntryPath($entryName);
                    if ($normalizedPath === null) {
                        continue;
                    }

                    $targetPath = $extractRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
                    if ($this->scriptsZipEntryIsDirectory($entryName)) {
                        File::ensureDirectoryExists($targetPath);
                        continue;
                    }

                    $stream = $zip->getStream($entryName);
                    if ($stream === false) {
                        throw new \RuntimeException('Could not read archive entry: ' . $entryName);
                    }

                    File::ensureDirectoryExists(dirname($targetPath));
                    $targetHandle = fopen($targetPath, 'wb');
                    if ($targetHandle === false) {
                        fclose($stream);
                        throw new \RuntimeException('Could not write extracted file: ' . $normalizedPath);
                    }

                    stream_copy_to_stream($stream, $targetHandle);
                    fclose($stream);
                    fclose($targetHandle);
                }

                $zip->close();
                $zip = null;
            } else {
                throw new \RuntimeException('Neither unzip command nor ZipArchive is available on this server.');
            }

            $updatedFiles = $this->applyImportedScripts($extractRoot, $scriptsRoot, true);
            if ($updatedFiles <= 0) {
                throw new \RuntimeException('Archive did not contain any files under scripts/.');
            }

            return redirect()
                ->route('settings.index')
                ->with('status', "Scripts backup imported successfully. Updated {$updatedFiles} file(s).");
        } catch (\Throwable $exception) {
            if ($zip instanceof \ZipArchive) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                    // Ignore close failures while handling the original error.
                }
            }

            return redirect()
                ->route('settings.index')
                ->withErrors([
                    'scripts_import' => 'Could not import scripts backup: ' . $exception->getMessage() . ' (Install zip/unzip and php-zip on server if needed.)',
                ]);
        } finally {
            if (is_string($temporaryImportRoot) && $temporaryImportRoot !== '' && File::isDirectory($temporaryImportRoot)) {
                File::deleteDirectory($temporaryImportRoot);
            }
        }
    }

    private function applyImportedScripts(string $extractRoot, string $scriptsRoot, bool $replaceExisting): int
    {
        $extractedScriptsRoot = $extractRoot . DIRECTORY_SEPARATOR . 'scripts';
        if (!File::isDirectory($extractedScriptsRoot)) {
            return 0;
        }

        if ($replaceExisting && File::isDirectory($scriptsRoot)) {
            foreach (File::allFiles($scriptsRoot) as $existingFile) {
                if ($existingFile->isFile()) {
                    File::delete($existingFile->getPathname());
                }
            }
        }

        $updatedFiles = 0;
        foreach (File::allFiles($extractedScriptsRoot) as $sourceFile) {
            if (!$sourceFile->isFile()) {
                continue;
            }

            $sourcePath = $sourceFile->getPathname();
            $relativePath = ltrim(Str::after($sourcePath, $extractedScriptsRoot), '\\/');
            $relativePath = str_replace('\\', '/', $relativePath);
            if ($relativePath === '' || str_contains($relativePath, '..')) {
                continue;
            }

            $targetPath = $scriptsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($sourcePath, $targetPath);

            if (str_ends_with(strtolower($targetPath), '.sh')) {
                @chmod($targetPath, 0755);
            }

            $updatedFiles++;
        }

        return $updatedFiles;
    }

    private function normalizeScriptsZipEntryPath(string $entryName): ?string
    {
        $path = trim(str_replace('\\', '/', $entryName));
        if ($path === '') {
            return null;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        if ($path === '' || str_contains($path, '..') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return null;
        }

        if ($path === 'scripts' || str_starts_with($path, 'scripts/')) {
            return $path;
        }

        return null;
    }

    private function scriptsZipEntryIsDirectory(string $entryName): bool
    {
        $entryName = str_replace('\\', '/', $entryName);
        return str_ends_with($entryName, '/');
    }

    private function createScriptsArchiveWithZipCommand(string $scriptsRoot, string $archivePath, array $manifestPayload): void
    {
        $stagingRoot = storage_path('app/exports/scripts/staging-' . (string) Str::uuid());
        try {
            File::ensureDirectoryExists($stagingRoot);
            $stagingScriptsRoot = $stagingRoot . DIRECTORY_SEPARATOR . 'scripts';
            File::ensureDirectoryExists($stagingScriptsRoot);
            File::copyDirectory($scriptsRoot, $stagingScriptsRoot);
            File::put(
                $stagingRoot . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $process = new Process(['zip', '-rq', $archivePath, 'scripts', 'manifest.json'], $stagingRoot);
            $process->setTimeout(180);
            $process->run();
            if (!$process->isSuccessful()) {
                $error = trim($process->getErrorOutput());
                $output = trim($process->getOutput());
                throw new \RuntimeException(
                    $error !== ''
                        ? $error
                        : ($output !== '' ? $output : 'zip command failed while building scripts backup archive.')
                );
            }

            if (!is_file($archivePath) || (int) filesize($archivePath) <= 0) {
                throw new \RuntimeException('Scripts backup archive was not created.');
            }
        } finally {
            if (File::isDirectory($stagingRoot)) {
                File::deleteDirectory($stagingRoot);
            }
        }
    }

    private function extractScriptsArchiveWithUnzipCommand(string $archivePath, string $extractRoot): void
    {
        $listProcess = new Process(['unzip', '-Z1', $archivePath]);
        $listProcess->setTimeout(60);
        $listProcess->run();
        if (!$listProcess->isSuccessful()) {
            $error = trim($listProcess->getErrorOutput());
            $output = trim($listProcess->getOutput());
            throw new \RuntimeException(
                $error !== ''
                    ? $error
                    : ($output !== '' ? $output : 'Could not inspect scripts backup archive with unzip.')
            );
        }

        $entries = preg_split('/\r\n|\r|\n/', trim($listProcess->getOutput())) ?: [];
        $writtenFiles = 0;

        foreach ($entries as $entryName) {
            $entryName = trim((string) $entryName);
            if ($entryName === '') {
                continue;
            }

            $normalizedPath = $this->normalizeScriptsZipEntryPath($entryName);
            if ($normalizedPath === null) {
                continue;
            }

            $targetPath = $extractRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
            if ($this->scriptsZipEntryIsDirectory($entryName)) {
                File::ensureDirectoryExists($targetPath);
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            $extractProcess = new Process(['unzip', '-p', $archivePath, $entryName]);
            $extractProcess->setTimeout(120);
            $extractProcess->run();
            if (!$extractProcess->isSuccessful()) {
                $error = trim($extractProcess->getErrorOutput());
                $output = trim($extractProcess->getOutput());
                throw new \RuntimeException(
                    $error !== ''
                        ? $error
                        : ($output !== '' ? $output : 'Could not extract archive entry: ' . $entryName)
                );
            }

            File::put($targetPath, $extractProcess->getOutput());
            $writtenFiles++;
        }

        if ($writtenFiles <= 0) {
            throw new \RuntimeException('Archive did not contain valid scripts entries.');
        }
    }

    private function commandBinaryExists(string $binary): bool
    {
        $binary = trim($binary);
        if ($binary === '') {
            return false;
        }

        $commonPaths = [
            '/usr/bin/' . $binary,
            '/bin/' . $binary,
            '/usr/local/bin/' . $binary,
        ];

        foreach ($commonPaths as $path) {
            if (is_file($path) && is_executable($path)) {
                return true;
            }
        }

        try {
            $process = new Process(['which', $binary]);
            $process->setTimeout(5);
            $process->run();
            return $process->isSuccessful() && trim($process->getOutput()) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildTimezoneEntries(): array
    {
        $entries = [];

        foreach (timezone_identifiers_list() as $timezone) {
            $parts = explode('/', $timezone, 2);
            $region = str_replace('_', ' ', $parts[0]);
            $label = str_replace('_', ' ', $parts[1] ?? $parts[0]);
            $countryCode = null;
            $countryName = 'Global / No Country';

            try {
                $location = timezone_location_get(new DateTimeZone($timezone));
                $countryCode = strtoupper((string) ($location['country_code'] ?? ''));
            } catch (\Throwable) {
                $countryCode = null;
            }

            if (is_string($countryCode) && $countryCode !== '') {
                $countryName = $this->resolveCountryName($countryCode);
            }

            $entries[] = [
                'timezone' => $timezone,
                'label' => $label,
                'region' => $region,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'display_label' => $countryName,
            ];
        }

        $countryCounts = [];
        foreach ($entries as $entry) {
            $countryCounts[$entry['country_name']] = ($countryCounts[$entry['country_name']] ?? 0) + 1;
        }

        foreach ($entries as &$entry) {
            $hasMultipleTimezones = ($countryCounts[$entry['country_name']] ?? 0) > 1;

            if ($entry['country_name'] === 'Global / No Country') {
                $entry['display_label'] = $entry['timezone'];
                continue;
            }

            $entry['display_label'] = $hasMultipleTimezones
                ? $entry['country_name'] . ', ' . $entry['label']
                : $entry['country_name'];
        }
        unset($entry);

        usort($entries, function (array $left, array $right): int {
            return [$left['country_name'], $left['display_label'], $left['timezone']]
                <=> [$right['country_name'], $right['display_label'], $right['timezone']];
        });

        return $entries;
    }

    private function buildCountryOptions(): array
    {
        $countries = [];

        foreach ($this->buildTimezoneEntries() as $entry) {
            $countries[$entry['country_name']] = $entry['country_name'];
        }

        ksort($countries);

        return array_values($countries);
    }

    private function resolveCountryName(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '') {
            return 'Global / No Country';
        }

        if ($countryCode === 'IL') {
            return 'Palastine';
        }

        if (class_exists('Locale')) {
            try {
                $countryName = \Locale::getDisplayRegion('-' . $countryCode, 'en');
                if (is_string($countryName) && trim($countryName) !== '') {
                    return $countryName;
                }
            } catch (\Throwable) {
                // Fall back to country code.
            }
        }

        return $countryCode;
    }

    private function runAllBackups()
    {
        try {
            $exitCode = Artisan::call('devices:run-backups');
            $output = trim((string) Artisan::output());
            $summary = $this->summarizeBackupCommandOutput($output);

            if ($exitCode === 0) {
                return redirect()
                    ->route('settings.index')
                    ->with('status', $summary !== '' ? $summary : 'Backups started for all devices.');
            }

            return back()->withErrors([
                'backups' => $summary !== '' ? $summary : 'The all-device backup run failed.',
            ]);
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'backups' => 'Could not run all-device backups: ' . $exception->getMessage(),
            ]);
        }
    }

    private function clearBackupsForAllDevices()
    {
        try {
            $result = $this->clearBackupDirectories(
                Device::query()->orderBy('id')->get(['id', 'name', 'metadata'])
            );

            return redirect()
                ->route('settings.index')
                ->with('status', "Cleared {$result['files_deleted']} backup file(s) across {$result['directories_touched']} device folder(s).");
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'backups' => 'Could not clear backups for all devices: ' . $exception->getMessage(),
            ]);
        }
    }

    private function clearBackupsForSingleDevice(int $deviceId)
    {
        try {
            $device = Device::query()->findOrFail($deviceId, ['id', 'name', 'metadata']);
            $result = $this->clearBackupDirectories([$device]);

            return redirect()
                ->route('settings.index')
                ->with('status', "Cleared {$result['files_deleted']} backup file(s) for {$device->name}.");
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'backups' => 'Could not clear device backups: ' . $exception->getMessage(),
            ])->withInput();
        }
    }

    private function clearBackupDirectories(iterable $devices): array
    {
        $directories = [];

        foreach ($devices as $device) {
            if (!$device instanceof Device) {
                continue;
            }

            $resolved = $this->resolveBackupDirectory($device);
            if (!$resolved || empty($resolved['absolute'])) {
                continue;
            }

            $directories[$resolved['absolute']] = $resolved;
        }

        $filesDeleted = 0;
        $directoriesTouched = 0;

        foreach ($directories as $resolved) {
            $absolute = $resolved['absolute'];
            if (!File::isDirectory($absolute)) {
                continue;
            }

            $directoriesTouched++;
            foreach (File::allFiles($absolute) as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                File::delete($file->getPathname());
                $filesDeleted++;
            }

            $this->removeEmptyChildDirectories($absolute);
        }

        return [
            'directories_touched' => $directoriesTouched,
            'files_deleted' => $filesDeleted,
        ];
    }

    private function removeEmptyChildDirectories(string $absolute): void
    {
        if (!File::isDirectory($absolute)) {
            return;
        }

        $directories = collect(File::allDirectories($absolute))
            ->sortByDesc(static fn (string $path): int => strlen($path))
            ->values();

        foreach ($directories as $directory) {
            if (File::isDirectory($directory) && count(File::files($directory)) === 0 && count(File::directories($directory)) === 0) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function summarizeBackupCommandOutput(string $output): string
    {
        if ($output === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (Str::startsWith($line, 'Backups attempted:')) {
                return $line;
            }
        }

        return trim((string) collect($lines)->filter()->last());
    }

    private function configurationBackupDatasets(): array
    {
        $datasets = [];

        foreach (array_keys($this->configurationBackupTableLabels()) as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'id')) {
                $query->orderBy('id');
            } elseif (Schema::hasColumn($table, 'key')) {
                $query->orderBy('key');
            }

            $datasets[$table] = $query
                ->get()
                ->map(static fn ($row): array => (array) $row)
                ->all();
        }

        return $datasets;
    }

    private function configurationBackupTableLabels(): array
    {
        return [
            'users' => 'Users',
            'devices' => 'Devices',
            'device_assignments' => 'Device assignments',
            'device_permissions' => 'Device permissions',
            'app_settings' => 'App settings',
        ];
    }

    private function decodeConfigurationBackup(UploadedFile $file): array
    {
        $content = File::get($file->getRealPath());
        $payload = json_decode($content, true);

        if (!is_array($payload)) {
            throw new \RuntimeException('The uploaded file is not valid JSON.');
        }

        if (($payload['type'] ?? null) !== 'device-control-system-config') {
            throw new \RuntimeException('The uploaded file is not a system configuration backup from this application.');
        }

        return $payload;
    }

    private function importConfigurationDatasets(array $datasets): string
    {
        $managedTables = array_keys($this->configurationBackupTableLabels());
        $resetTables = [
            'notifications',
            'alerts',
            'telemetry_logs',
            'device_assignments',
            'device_permissions',
            'devices',
            'app_settings',
            'password_reset_tokens',
            'sessions',
            'users',
        ];

        $foreignKeyChecksDisabled = $this->setForeignKeyChecks(false);

        try {
            DB::beginTransaction();

            foreach ($resetTables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            $importCounts = [];
            foreach ($managedTables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $rows = $this->normalizeImportRows($table, $datasets[$table] ?? []);
                $importCounts[$table] = count($rows);

                if ($rows !== []) {
                    foreach (array_chunk($rows, 250) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->setForeignKeyChecks(true, $foreignKeyChecksDisabled);
            throw $exception;
        }

        $this->setForeignKeyChecks(true, $foreignKeyChecksDisabled);

        return collect($importCounts)
            ->map(static fn (int $count, string $table): string => "{$count} {$table}")
            ->implode(', ');
    }

    private function normalizeImportRows(string $table, mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $columns = array_flip(Schema::getColumnListing($table));

        return collect($rows)
            ->map(static function ($row) use ($columns): array {
                $data = is_array($row) ? $row : (is_object($row) ? (array) $row : []);
                $normalized = [];

                foreach ($data as $key => $value) {
                    if (!is_string($key) || !isset($columns[$key])) {
                        continue;
                    }

                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    }

                    $normalized[$key] = $value;
                }

                return $normalized;
            })
            ->filter(static fn (array $row): bool => $row !== [])
            ->values()
            ->all();
    }

    private function setForeignKeyChecks(bool $enabled, ?bool $applied = null): bool
    {
        if ($applied === false) {
            return false;
        }

        $driver = DB::getDriverName();

        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=' . ($enabled ? '1' : '0'));
                return true;
            }

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ' . ($enabled ? 'ON' : 'OFF'));
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function parseCleanupRange(string $rangeStartRaw, string $rangeEndRaw): array
    {
        $rangeStart = Carbon::parse(trim($rangeStartRaw));
        $rangeEnd = Carbon::parse(trim($rangeEndRaw));

        if ($rangeEnd->lessThan($rangeStart)) {
            $swap = $rangeStart;
            $rangeStart = $rangeEnd;
            $rangeEnd = $swap;
        }

        return [$rangeStart, $rangeEnd];
    }

    private function applyDateRangeToQuery(mixed $query, string $primaryColumn, string $fallbackColumn, Carbon $rangeStart, Carbon $rangeEnd): void
    {
        $query->where(function ($rangeQuery) use ($primaryColumn, $fallbackColumn, $rangeStart, $rangeEnd): void {
            $rangeQuery->whereBetween($primaryColumn, [$rangeStart, $rangeEnd]);

            if ($fallbackColumn !== '' && $fallbackColumn !== $primaryColumn) {
                $rangeQuery->orWhere(function ($fallbackRangeQuery) use ($primaryColumn, $fallbackColumn, $rangeStart, $rangeEnd): void {
                    $fallbackRangeQuery
                        ->whereNull($primaryColumn)
                        ->whereBetween($fallbackColumn, [$rangeStart, $rangeEnd]);
                });
            }
        });
    }

    private function applyEventTimestampRangeToQuery(mixed $query, ?int $rangeStartTimestamp, ?int $rangeEndTimestamp): void
    {
        if ($rangeStartTimestamp !== null) {
            $query->where(function ($rangeQuery) use ($rangeStartTimestamp): void {
                $rangeQuery
                    ->where('opened_at', '>=', $rangeStartTimestamp)
                    ->orWhere('resolved_at', '>=', $rangeStartTimestamp);
            });
        }

        if ($rangeEndTimestamp !== null) {
            $query->where(function ($rangeQuery) use ($rangeEndTimestamp): void {
                $rangeQuery
                    ->where('opened_at', '<=', $rangeEndTimestamp)
                    ->orWhere('resolved_at', '<=', $rangeEndTimestamp);
            });
        }
    }

    private function cleanupAutoSettingFor(string $scope): array
    {
        $default = [
            'enabled' => false,
            'frequency' => 'weekly',
        ];

        if (!in_array($scope, ['logs', 'notifications', 'events'], true)) {
            return $default;
        }

        if (!AppSetting::supportsStorage()) {
            return $default;
        }

        $enabledRaw = AppSetting::getValue($this->cleanupAutoSettingKey($scope, 'enabled'), '0');
        $frequencyRaw = strtolower(trim((string) AppSetting::getValue(
            $this->cleanupAutoSettingKey($scope, 'frequency'),
            $default['frequency']
        )));
        if (!in_array($frequencyRaw, ['weekly', 'monthly', 'yearly'], true)) {
            $frequencyRaw = $default['frequency'];
        }

        return [
            'enabled' => in_array(strtolower(trim((string) $enabledRaw)), ['1', 'true', 'yes', 'on'], true),
            'frequency' => $frequencyRaw,
        ];
    }

    private function saveCleanupAutoSetting(string $scope, bool $enabled, string $frequency): void
    {
        if (!in_array($scope, ['logs', 'notifications', 'events'], true)) {
            throw new \InvalidArgumentException('Invalid cleanup scope.');
        }

        if (!in_array($frequency, ['weekly', 'monthly', 'yearly'], true)) {
            throw new \InvalidArgumentException('Invalid cleanup frequency.');
        }

        AppSetting::putValue($this->cleanupAutoSettingKey($scope, 'enabled'), $enabled ? '1' : '0');
        AppSetting::putValue($this->cleanupAutoSettingKey($scope, 'frequency'), $frequency);
    }

    private function cleanupAutoSettingKey(string $scope, string $field): string
    {
        return 'cleanup_' . $scope . '_auto_' . $field;
    }

    private function tableCount(string $table): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function mysqldumpBinary(): string
    {
        $configured = trim((string) env('MYSQLDUMP_BINARY', ''));
        return $configured !== '' ? $configured : 'mysqldump';
    }

    private function uploadBackupFileToFtp(string $localPath, string $remoteFileName, string $host, string $username, string $password): void
    {
        if (!function_exists('ftp_connect')) {
            throw new \RuntimeException('PHP FTP extension is not enabled on this server.');
        }

        $connection = @ftp_connect($host, 21, 20);
        if ($connection === false) {
            throw new \RuntimeException('Could not connect to FTP server: ' . $host);
        }

        try {
            $loggedIn = @ftp_login($connection, $username, $password);
            if (!$loggedIn) {
                throw new \RuntimeException('FTP authentication failed.');
            }

            @ftp_pasv($connection, true);

            $uploaded = @ftp_put($connection, $remoteFileName, $localPath, FTP_BINARY);
            if (!$uploaded) {
                throw new \RuntimeException('FTP upload failed for file: ' . $remoteFileName);
            }
        } finally {
            @ftp_close($connection);
        }
    }

    private function resolveBackupDirectory(Device $device): ?array
    {
        $meta = $device->metadata ?? [];
        $cisco = data_get($meta, 'cisco', []);
        $relative = $this->firstNonEmpty(
            data_get($cisco, 'folder_location'),
            data_get($meta, 'folder_location')
        );

        if (!$relative) {
            $fallbackName = $this->firstNonEmpty(data_get($cisco, 'name'), $device->name, 'device');
            $fallbackSlug = preg_replace('/\s+/', '_', (string) $fallbackName);
            $relative = 'uno/' . ($fallbackSlug !== '' ? $fallbackSlug : 'device');
        }

        return $this->resolveBackupDirectoryFromRelative($relative);
    }

    private function resolveBackupDirectoryFromRelative(string $relative): ?array
    {
        $relative = trim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $roots = $this->backupRoots();
        $firstCandidate = null;

        foreach ($roots as $root) {
            $candidate = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($firstCandidate === null) {
                $firstCandidate = $candidate;
            }

            if (is_dir($candidate)) {
                return [
                    'relative' => $relative,
                    'absolute' => $candidate,
                ];
            }
        }

        return [
            'relative' => $relative,
            'absolute' => $firstCandidate ?: base_path($relative),
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

        return array_values(array_unique(array_map(
            static fn (string $root): string => rtrim(str_replace('\\', '/', $root), '/'),
            array_filter(array_merge($roots, [
                '/srv/tftp',
                '/srv/tftpboot',
                '/var/lib/tftpboot',
                '/var/tftpboot',
                '/tftpboot',
                base_path(),
                '/var/www/html',
            ]))
        )));
    }

    private function backupScriptInventory(): array
    {
        $scriptsDirectory = base_path('scripts');
        if (!File::isDirectory($scriptsDirectory)) {
            return [];
        }

        return collect(File::allFiles($scriptsDirectory))
            ->filter(static fn (\SplFileInfo $file): bool => $file->isFile())
            ->sortBy(static fn (\SplFileInfo $file): string => str_replace('\\', '/', $file->getPathname()))
            ->values()
            ->map(static function (\SplFileInfo $file) use ($scriptsDirectory): array {
                $path = $file->getPathname();
                $relativePath = ltrim(Str::after($path, $scriptsDirectory), '\\/');
                $relativePath = str_replace('\\', '/', $relativePath);

                $mtime = @filemtime($path);
                $size = @filesize($path);
                $permissionOctal = @substr(sprintf('%o', (int) @fileperms($path)), -4);

                return [
                    'name' => $relativePath !== '' ? $relativePath : $file->getFilename(),
                    'path' => str_replace('\\', '/', $path),
                    'exists' => true,
                    'permissions' => $permissionOctal !== false ? $permissionOctal : null,
                    'size_bytes' => is_int($size) ? $size : null,
                    'modified_at' => is_int($mtime) ? date('Y-m-d H:i:s', $mtime) : null,
                ];
            })
            ->all();
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

    /**
     * @return array{
     *     event_types_custom:string,
     *     default:string,
     *     low:string,
     *     medium:string,
     *     high:string,
     *     critical:string
     * }
     */
    private function telegramTemplateSettings(): array
    {
        $defaults = [
            'event_types_custom' => '',
            'default' => '',
            'low' => '',
            'medium' => '',
            'high' => '',
            'critical' => '',
        ];

        if (!AppSetting::supportsStorage()) {
            return $defaults;
        }

        $defaults['event_types_custom'] = trim((string) AppSetting::getValue(self::TELEGRAM_EVENT_TYPES_CUSTOM_SETTING, ''));

        foreach (self::TELEGRAM_TEMPLATE_SETTINGS as $fieldName => $settingKey) {
            $defaults[$fieldName] = trim((string) AppSetting::getValue($settingKey, ''));
        }

        return $defaults;
    }

    private function normalizeTelegramEventTypesCustom(string $value): string
    {
        $tokens = preg_split('/\s*,\s*/', trim($value)) ?: [];
        $items = [];
        foreach ($tokens as $token) {
            $normalized = strtolower(trim((string) $token));
            if ($normalized !== '') {
                $items[] = $normalized;
            }
        }

        $items = array_values(array_unique($items));
        return implode(',', $items);
    }

    private function backupTftpServerAddress(): string
    {
        $configured = AppSetting::supportsStorage()
            ? AppSetting::getValue('backup_tftp_server_address')
            : null;

        $normalized = $this->normalizeOptionalString(is_scalar($configured) ? (string) $configured : null);
        if ($normalized !== null) {
            return $normalized;
        }

        return (string) env('BACKUP_TFTP_SERVER', '');
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }
}
