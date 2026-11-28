<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Clustering schedule from System settings (CLAUDE.md §7 M6). Runs as a queue job, never in a web request.
Schedule::command('clustering:scheduled')->dailyAt('02:30')->withoutOverlapping();

// Dashboard summary figures (§7 M7): counted nightly so the dashboards load fast.
Schedule::command('analytics:snapshot')->dailyAt('02:15')->withoutOverlapping();

// Backups (§7 M9): encrypted daily backup with an off-server copy, then retention cleanup;
// a health check each morning; and a monthly restore test to the staging database.
Schedule::command('repository:backup')->dailyAt((string) config('tua.backup.daily_at'))->withoutOverlapping(120);
Schedule::command('backup:monitor')->dailyAt('07:00');
Schedule::command('repository:restore-test')->monthlyOn(1, '04:00')->withoutOverlapping(120);

// Activity log retention (§7 M8): entries older than the years set in System settings are removed nightly.
Schedule::command('audit:prune')->dailyAt('03:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
