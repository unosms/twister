<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\BackupSchedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:poll-status')->everyMinute()->withoutOverlapping();
Schedule::command('events:poll')->everyMinute()->withoutOverlapping();
BackupSchedule::applyTo(app(\Illuminate\Console\Scheduling\Schedule::class));
