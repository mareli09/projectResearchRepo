<?php

namespace App\Domain\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Restores (CLAUDE.md §7 M9): a backup is first restored to the staging database and checked.
 * Only a backup that passed that check can be promoted to the live database, from the command
 * line (php artisan backup:promote), with a safety backup taken first.
 */
final class BackupRestorer
{
    /** Tables compared between the restored copy and the live database. */
    public const CHECK_TABLES = ['users', 'research_records', 'record_files', 'submissions', 'activity_log', 'search_logs', 'cluster_runs', 'site_contents'];

    public static function stagingDatabase(): string
    {
        $name = (string) config('tua.backup.staging_database');
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            throw new RuntimeException('BACKUP_STAGING_DATABASE must use only letters, numbers and underscores.');
        }
        if ($name === (string) config('database.connections.mysql.database')) {
            throw new RuntimeException('The staging database cannot be the live database.');
        }

        return $name;
    }

    /**
     * Restores one backup to the staging database and compares it with the live database.
     * The result is also saved as the setting backup.last_restore.
     *
     * @return array{file: string, kind: string, by: ?string, at: string, ok: bool, tables: array<string, array{backup: ?int, live: ?int}>, files_in_backup: int, files_in_records: ?int, error: ?string, seconds: int, note?: string}
     */
    public function toStaging(string $file, ?User $by, string $kind = 'manual'): array
    {
        $started = microtime(true);
        $result = ['file' => $file, 'kind' => $kind, 'by' => $by?->name, 'at' => now()->toIso8601String(), 'ok' => false,
            'tables' => [], 'files_in_backup' => 0, 'files_in_records' => null, 'error' => null, 'seconds' => 0];
        $work = null;
        Cache::put(Backups::BUSY_KEY, 'Restoring '.$file.' to staging', 3600);

        try {
            $staging = self::stagingDatabase();
            [$sql, $work, $files] = $this->extractDump($file);
            $result['files_in_backup'] = $files;

            $this->ensureStagingExists($staging);
            $this->import($sql, $staging);

            $conn = $this->stagingConnection($staging);
            foreach (self::CHECK_TABLES as $table) {
                $result['tables'][$table] = [
                    'backup' => $this->countOrNull($conn, $table),
                    'live' => $this->countOrNull('mysql', $table),
                ];
            }
            $result['files_in_records'] = $this->countOrNull($conn, 'record_files');
            $missing = collect($result['tables'])->filter(fn ($t) => $t['backup'] === null)->keys()->all();
            $migrations = [$this->countOrNull($conn, 'migrations'), $this->countOrNull('mysql', 'migrations')];

            if ($missing !== []) {
                throw new RuntimeException('The restored copy is missing tables: '.implode(', ', $missing).'.');
            }
            if ($migrations[0] !== $migrations[1]) {
                $result['note'] = "The backup has {$migrations[0]} migrations and the live database has {$migrations[1]}. Run php artisan migrate after promoting it.";
            }
            $result['ok'] = true;
        } catch (Throwable $e) {
            $result['error'] = Str::limit($e->getMessage(), 400);
        } finally {
            if ($work !== null) {
                File::deleteDirectory($work);
            }
            Cache::forget(Backups::BUSY_KEY);
        }

        $result['seconds'] = (int) round(microtime(true) - $started);
        Setting::updateOrCreate(['key' => 'backup.last_restore'], ['value' => $result]);

        $label = $kind === 'monthly' ? 'Monthly restore test' : 'Restore to staging';
        Audit::record(AuditEvent::BACKUP, $result['ok'] ? "{$label} passed: {$file}" : "{$label} failed: {$file}: {$result['error']}", null,
            ['result' => $result['ok'] ? 'ok' : 'failed', 'file' => $file, 'kind' => $kind, 'seconds' => $result['seconds']], $by);
        if (! $result['ok']) {
            Backups::alertAdmins("{$label} failed", "Restoring {$file} to the staging database failed: {$result['error']}");
        }

        return $result;
    }

    /**
     * Promotes a backup to the live database and the research files folder. Command line only.
     * The same backup must have passed a restore to staging first.
     */
    public function toLive(string $file, ?User $by = null): void
    {
        $last = Backups::lastRestore();
        if (($last['file'] ?? null) !== $file || ($last['ok'] ?? false) !== true) {
            throw new RuntimeException("Restore {$file} to staging first. Only a backup that passed the staging check can be promoted.");
        }

        [$sql, $work] = $this->extractDump($file, withFiles: true);
        try {
            $this->import($sql, (string) config('database.connections.mysql.database'));
            if (is_dir($work.'/research')) {
                File::copyDirectory($work.'/research', storage_path('app/research'));
            }
        } finally {
            File::deleteDirectory($work);
        }

        Audit::record(AuditEvent::BACKUP, "Backup promoted to the live database: {$file}", null, ['result' => 'ok', 'file' => $file, 'kind' => 'promote'], $by);
    }

    /**
     * Extracts the database dump (and, when asked, the research files) to a temporary folder.
     *
     * @return array{0: string, 1: string, 2: int} path of the .sql file, the temporary folder, research files in the zip
     */
    private function extractDump(string $file, bool $withFiles = false): array
    {
        $path = Backups::path($file) ?? throw new RuntimeException("There is no backup named {$file}.");
        $inside = Backups::inspect($path);
        if ($inside['db_entry'] === null) {
            throw new RuntimeException('This backup has no database dump.');
        }

        $work = storage_path('app/backup-temp/restore-'.Str::lower(Str::random(12)));
        File::ensureDirectoryExists($work);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The backup file could not be opened.');
        }
        if ($inside['encrypted']) {
            $zip->setPassword((string) config('backup.backup.password'));
        }
        $entries = [$inside['db_entry']];
        if ($withFiles) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                // Only plain relative paths inside research/ (no "..", no absolute paths).
                if (str_starts_with(str_replace('\\', '/', $name), 'research/') && ! str_contains($name, '..') && ! str_ends_with($name, '/')) {
                    $entries[] = $name;
                }
            }
        }
        $ok = $zip->extractTo($work, $entries);
        $zip->close();
        $sql = $work.'/'.str_replace('\\', '/', $inside['db_entry']);
        if (! $ok || ! is_file($sql) || filesize($sql) === 0) {
            File::deleteDirectory($work);
            throw new RuntimeException('The backup could not be decrypted. Check BACKUP_ARCHIVE_PASSWORD.');
        }

        return [$sql, $work, $inside['files']];
    }

    private function ensureStagingExists(string $staging): void
    {
        // Its own connection: DDL commits implicitly in MySQL, so it must not share the app's session.
        config(['database.connections.backup_admin' => config('database.connections.mysql')]);
        DB::purge('backup_admin');
        $admin = DB::connection('backup_admin');

        $exists = $admin->selectOne('SELECT SCHEMA_NAME AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$staging]);
        if ($exists !== null) {
            return;
        }
        try {
            $admin->statement("CREATE DATABASE `{$staging}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Throwable) {
            throw new RuntimeException("The staging database {$staging} does not exist. Ask the ICT Office to create it and give the app account rights on it.");
        }
    }

    /** Runs the dump through the mysql client. The password goes in MYSQL_PWD, never on the command line. */
    private function import(string $sql, string $database): void
    {
        $c = config('database.connections.mysql');
        $bin = rtrim((string) config('tua.backup.mysql_bin'), '/\\');
        $mysql = $bin === '' ? 'mysql' : $bin.'/mysql';

        $handle = fopen($sql, 'r');
        if ($handle === false) {
            throw new RuntimeException('The database dump could not be read.');
        }
        try {
            $process = Process::timeout(3600)
                ->env(array_filter(['MYSQL_PWD' => (string) ($c['password'] ?? '')]))
                ->input($handle)
                ->run([$mysql, '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'], '--default-character-set=utf8mb4', $database]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($process->failed()) {
            throw new RuntimeException('mysql: '.Str::limit(trim($process->errorOutput()) ?: 'exit code '.$process->exitCode(), 300));
        }
    }

    private function stagingConnection(string $staging): string
    {
        config(['database.connections.staging' => array_merge(config('database.connections.mysql'), ['database' => $staging])]);
        DB::purge('staging');

        return 'staging';
    }

    private function countOrNull(string $connection, string $table): ?int
    {
        try {
            return DB::connection($connection)->table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
