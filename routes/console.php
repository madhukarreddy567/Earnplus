<?php

use App\Services\CronJobRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Phase 11: scheduled jobs
|--------------------------------------------------------------------------
|
| Every job is defined once in CronJobRegistry::jobs(); the admin cron
| dashboard and manual "Run now" buttons read the same registry.
| withoutOverlapping() guarantees a slow run never piles up behind
| itself. Add ONE system cron entry on the server and everything runs:
|
|   * * * * * cd /path/to/earnplus && php artisan schedule:run >> /dev/null 2>&1
|
*/
foreach (CronJobRegistry::jobs() as $job) {
    Schedule::command($job['signature'])
        ->cron($job['expression'])
        ->withoutOverlapping()
        ->name("cron:{$job['key']}");
}

// Heartbeat: lets the admin dashboard prove the system cron is alive.
Schedule::call(function () {
    CronJobRegistry::beat();
})->everyMinute()->name('cron:heartbeat');
