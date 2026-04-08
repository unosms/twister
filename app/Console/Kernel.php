<?php

namespace App\Console;

use App\Console\Commands\PollDeviceEvents;
use App\Console\Commands\PollDeviceStatus;
use App\Console\Commands\RunAutomaticCleanup;
use App\Console\Commands\RunDeviceBackups;
use App\Support\BackupSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        PollDeviceEvents::class,
        PollDeviceStatus::class,
        RunAutomaticCleanup::class,
        RunDeviceBackups::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('devices:poll-status')->everyMinute()->withoutOverlapping();
        $schedule->command('events:poll')->everyMinute()->withoutOverlapping();
        $schedule->command('cleanup:auto')->hourly()->withoutOverlapping();
        BackupSchedule::applyTo($schedule);
    }
}
