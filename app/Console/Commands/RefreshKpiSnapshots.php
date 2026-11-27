<?php

namespace App\Console\Commands;

use App\Domain\Analytics\KpiSnapshots;
use Illuminate\Console\Command;

/** Nightly KPI snapshots for the dashboards (CLAUDE.md §7 M7 "Speed"). Scheduled at 2:15 AM. */
class RefreshKpiSnapshots extends Command
{
    protected $signature = 'analytics:snapshot';

    protected $description = 'Count the dashboard summary figures into kpi_snapshots';

    public function handle(): int
    {
        $started = microtime(true);
        $rows = KpiSnapshots::refresh();
        $this->info(sprintf('%d snapshot rows written in %.1f s.', $rows, microtime(true) - $started));

        return self::SUCCESS;
    }
}
