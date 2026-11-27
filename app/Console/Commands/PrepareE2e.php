<?php

namespace App\Console\Commands;

use Database\Seeders\Support\SampleDocuments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Prepares the end-to-end test run (CLAUDE.md §16 "Mobile tests", Playwright): a separate
 * database with the demo users and sample records, and unique test files for each browser project
 * (the exact-duplicate check would stop a second run with the same files). Never in production.
 */
class PrepareE2e extends Command
{
    protected $signature = 'e2e:prepare {--database=tua_repository_e2e}';

    protected $description = 'Create and seed the Playwright test database and write the upload test files';

    public const PROJECTS = ['desktop', 'pixel-7', 'iphone-14'];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }
        $db = (string) $this->option('database');
        if (! preg_match('/^[A-Za-z0-9_]+_e2e$/', $db)) {
            $this->error('The test database name must end in _e2e.');

            return self::FAILURE;
        }

        DB::statement("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(['database.connections.mysql.database' => $db]);
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true], $this->output);

        $dir = base_path('tests/Browser/.fixtures');
        File::ensureDirectoryExists($dir);
        foreach (self::PROJECTS as $p) {
            $tag = $p.' '.bin2hex(random_bytes(6));
            File::put("{$dir}/{$p}-abstract.docx", SampleDocuments::docx("Abstract {$tag}"));
            File::put("{$dir}/{$p}-paper.pdf", SampleDocuments::pdf("Full paper {$tag}"));
            File::put("{$dir}/{$p}-imrad.pdf", SampleDocuments::pdf("IMRAD {$tag}"));
            File::put("{$dir}/{$p}-toa.pdf", SampleDocuments::pdf("Transfer of ownership {$tag}"));
            File::put("{$dir}/{$p}-references.docx", SampleDocuments::docx("References {$tag}"));
        }
        $this->info("Seeded {$db} and wrote test files to tests/Browser/.fixtures.");

        return self::SUCCESS;
    }
}
