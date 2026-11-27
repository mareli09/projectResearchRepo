<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Clustering schedule from System settings (CLAUDE.md §7 M6). Runs as a queue job, never in a web request.
Schedule::command('clustering:scheduled')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
