<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AppSetting;
use App\Models\SystemNotification;
use App\Models\TelemetryLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RunAutomaticCleanup extends Command
{
    protected $signature = 'cleanup:auto';

    protected $description = 'Run automatic cleanup for logs, notifications, and events based on configured settings.';

    public function handle(): int
    {
        if (!AppSetting::supportsStorage()) {
            $this->warn('App settings storage is unavailable. Skipping automatic cleanup.');
            return self::SUCCESS;
        }

        $totalDeleted = 0;
        foreach (['logs', 'notifications', 'events'] as $scope) {
            try {
                $totalDeleted += $this->runScopeCleanup($scope);
            } catch (\Throwable $exception) {
                Log::warning('auto cleanup scope failed', [
                    'scope' => $scope,
                    'error' => $exception->getMessage(),
                ]);
                $this->error("Auto cleanup failed for {$scope}: {$exception->getMessage()}");
            }
        }

        $this->info("Auto cleanup completed. Deleted {$totalDeleted} row(s).");

        return self::SUCCESS;
    }

    private function runScopeCleanup(string $scope): int
    {
        $enabled = $this->isScopeEnabled($scope);
        if (!$enabled) {
            return 0;
        }

        $frequency = $this->frequencyFor($scope);
        $intervalSeconds = $this->intervalSecondsFor($frequency);
        $nowTs = now()->timestamp;
        $lastRunTs = (int) AppSetting::getValue($this->settingKey($scope, 'last_run_at'), 0);
        if ($lastRunTs > 0 && ($nowTs - $lastRunTs) < $intervalSeconds) {
            return 0;
        }

        $cutoffAt = now()->subSeconds($intervalSeconds);
        $deleted = match ($scope) {
            'logs' => $this->cleanupLogsOlderThan($cutoffAt),
            'notifications' => $this->cleanupNotificationsOlderThan($cutoffAt),
            'events' => $this->cleanupEventsOlderThan($cutoffAt),
            default => 0,
        };

        AppSetting::putValue($this->settingKey($scope, 'last_run_at'), (string) $nowTs);

        $this->line("Auto cleanup {$scope}: deleted {$deleted} row(s), frequency={$frequency}.");

        return $deleted;
    }

    private function cleanupLogsOlderThan(Carbon $cutoffAt): int
    {
        if (!Schema::hasTable('telemetry_logs')) {
            return 0;
        }

        $query = TelemetryLog::query();
        $this->applyDateCutoffToQuery($query, 'recorded_at', 'created_at', $cutoffAt);

        return $query->delete();
    }

    private function cleanupNotificationsOlderThan(Carbon $cutoffAt): int
    {
        $deletedNotifications = 0;
        $deletedAlerts = 0;

        DB::transaction(function () use ($cutoffAt, &$deletedNotifications, &$deletedAlerts): void {
            if (Schema::hasTable('notifications')) {
                $notificationQuery = SystemNotification::query();
                $this->applyDateCutoffToQuery($notificationQuery, 'created_at', 'updated_at', $cutoffAt);
                $deletedNotifications = $notificationQuery->delete();
            }

            if (Schema::hasTable('alerts')) {
                $alertsQuery = Alert::query();
                $this->applyDateCutoffToQuery($alertsQuery, 'created_at', 'updated_at', $cutoffAt);
                $deletedAlerts = $alertsQuery->delete();
            }
        });

        return $deletedNotifications + $deletedAlerts;
    }

    private function cleanupEventsOlderThan(Carbon $cutoffAt): int
    {
        $cutoffTs = $cutoffAt->timestamp;
        $deletedInterface = 0;
        $deletedDevice = 0;

        DB::transaction(function () use ($cutoffTs, &$deletedInterface, &$deletedDevice): void {
            if (Schema::hasTable('interface_events')) {
                $interfaceQuery = DB::table('interface_events');
                $this->applyEventCutoffToQuery($interfaceQuery, $cutoffTs);
                $deletedInterface = $interfaceQuery->delete();
            }

            if (Schema::hasTable('device_events')) {
                $deviceQuery = DB::table('device_events');
                $this->applyEventCutoffToQuery($deviceQuery, $cutoffTs);
                $deletedDevice = $deviceQuery->delete();
            }
        });

        return $deletedInterface + $deletedDevice;
    }

    private function applyDateCutoffToQuery(mixed $query, string $primaryColumn, string $fallbackColumn, Carbon $cutoffAt): void
    {
        $query->where(function ($where) use ($primaryColumn, $fallbackColumn, $cutoffAt): void {
            $where->where($primaryColumn, '<=', $cutoffAt);

            if ($fallbackColumn !== '' && $fallbackColumn !== $primaryColumn) {
                $where->orWhere(function ($fallbackWhere) use ($primaryColumn, $fallbackColumn, $cutoffAt): void {
                    $fallbackWhere
                        ->whereNull($primaryColumn)
                        ->where($fallbackColumn, '<=', $cutoffAt);
                });
            }
        });
    }

    private function applyEventCutoffToQuery(mixed $query, int $cutoffTs): void
    {
        $query->where(function ($where) use ($cutoffTs): void {
            $where
                ->where('opened_at', '<=', $cutoffTs)
                ->orWhere(function ($resolvedWhere) use ($cutoffTs): void {
                    $resolvedWhere
                        ->whereNull('opened_at')
                        ->where('resolved_at', '<=', $cutoffTs);
                });
        });
    }

    private function isScopeEnabled(string $scope): bool
    {
        $value = strtolower(trim((string) AppSetting::getValue($this->settingKey($scope, 'enabled'), '0')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function frequencyFor(string $scope): string
    {
        $value = strtolower(trim((string) AppSetting::getValue($this->settingKey($scope, 'frequency'), 'weekly')));
        if (!in_array($value, ['weekly', 'monthly', 'yearly'], true)) {
            return 'weekly';
        }

        return $value;
    }

    private function intervalSecondsFor(string $frequency): int
    {
        return match ($frequency) {
            'yearly' => 365 * 24 * 60 * 60,
            'monthly' => 30 * 24 * 60 * 60,
            default => 7 * 24 * 60 * 60,
        };
    }

    private function settingKey(string $scope, string $field): string
    {
        return 'cleanup_' . $scope . '_auto_' . $field;
    }
}
