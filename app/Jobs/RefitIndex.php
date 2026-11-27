<?php

namespace App\Jobs;

use App\Domain\Screening\Screener;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Refit the TF-IDF index on all published records (CLAUDE.md §7 M5). Only one at a time. */
class RefitIndex implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function handle(Screener $screener): void
    {
        $screener->fit();
    }
}
