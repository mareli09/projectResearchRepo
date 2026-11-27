<?php

namespace App\Console\Commands;

use App\Domain\Screening\TestSet;
use App\Services\AnalyticsClient\AnalyticsClient;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Loads pairs for the labeled screening test set (CLAUDE.md §17: "a seeder loads the labeled
 * screening test set"). Only the pairs: the labels must come from 3 coordinators.
 */
class SampleTestSet extends Command
{
    protected $signature = 'testset:sample {n=100 : How many pairs to add}';

    protected $description = 'Add study-record pairs from the repository for coordinators to label';

    public function handle(AnalyticsClient $analytics): int
    {
        try {
            $added = TestSet::sample(max(2, min(500, (int) $this->argument('n'))), null, $analytics);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("{$added} pairs added. Coordinators label them under \"Label test pairs\"; add prepared near-duplicates with the CSV import.");

        return self::SUCCESS;
    }
}
