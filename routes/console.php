<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:poll-status')->everyMinute()->withoutOverlapping();
Schedule::command('events:poll')->everyMinute()->withoutOverlapping();
Schedule::command('devices:run-backups')->everyTwoHours()->withoutOverlapping();
