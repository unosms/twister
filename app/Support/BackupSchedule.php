<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Console\Scheduling\Schedule;

class BackupSchedule
{
    private const DEFAULT_INTERVAL_HOURS = 2;

    private const ALLOWED_INTERVALS = [1, 2, 3, 4, 6, 8, 12, 24];

    public static function allowedIntervals(): array
    {
        return self::ALLOWED_INTERVALS;
    }

    public static function defaultIntervalHours(): int
    {
        return self::DEFAULT_INTERVAL_HOURS;
    }

    public static function intervalHours(): int
    {
        $stored = (int) AppSetting::getValue('backup_schedule_interval_hours', self::DEFAULT_INTERVAL_HOURS);

        return in_array($stored, self::ALLOWED_INTERVALS, true)
            ? $stored
            : self::DEFAULT_INTERVAL_HOURS;
    }

    public static function humanLabel(?int $hours = null): string
    {
        $hours = $hours ?? self::intervalHours();

        if ($hours === 24) {
            return 'Every 24 hours';
        }

        return $hours === 1
            ? 'Every hour'
            : "Every {$hours} hours";
    }

    public static function applyTo(Schedule $schedule): void
    {
        $hours = self::intervalHours();
        $event = $schedule->command('devices:run-backups')->withoutOverlapping();

        if ($hours === 1) {
            $event->hourly();
            return;
        }

        if ($hours === 24) {
            $event->daily();
            return;
        }

        $event->cron("0 */{$hours} * * *");
    }
}
