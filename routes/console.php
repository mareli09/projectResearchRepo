<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Clustering schedule from System settings (CLAUDE.md §7 M6). Runs as a queue job, never in a web request.
Schedule::command('clustering:scheduled')->dailyAt('02:30')->withoutOverlapping();

// Backups (§7 M9): encrypted daily backup with an off-server copy, then retention cleanup;
// a health check each morning; and a monthly restore test to the staging database.
Schedule::command('repository:backup')->dailyAt((string) config('tua.backup.daily_at'))->withoutOverlapping(120);
Schedule::command('backup:monitor')->dailyAt('07:00');
Schedule::command('repository:restore-test')->monthlyOn(1, '04:00')->withoutOverlapping(120);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
