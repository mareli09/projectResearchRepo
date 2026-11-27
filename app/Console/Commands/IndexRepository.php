<?php

namespace App\Console\Commands;

use App\Domain\Screening\Screener;
use Illuminate\Console\Command;
use Throwable;

/** php artisan repository:index — build the TF-IDF index in the analytics service now. */
class IndexRepository extends Command
{
    protected $signature = 'repository:index';

    protected $description = 'Fit the TF-IDF index of the analytics service on all published records';

    public function handle(Screener $screener): int
    {
        try {
            $r = $screener->fit();
        } catch (Throwable $e) {
            $this->error('The analytics service could not be reached: '.$e->getMessage());
            $this->line('Start it with: composer analytics');

            return self::FAILURE;
        }
        $this->info("Indexed {$r['n_docs']} records, {$r['n_terms']} terms (version {$r['vectorizer_version']}).");

        return self::SUCCESS;
    }
}
