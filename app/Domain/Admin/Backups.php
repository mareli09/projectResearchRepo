<?php

namespace App\Domain\Admin;

use App\Domain\Audit\Activity;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;
use ZipArchive;

/**
 * "Backups" (CLAUDE.md §7 M9): encrypted daily database and file backups made with
 * spatie/laravel-backup, an off-server copy, and restores that always go to staging first.
 */
final class Backups
{
    public const NAME = 'tua-repository';

    public const DISK = 'backups';

    public const OFFSITE_DISK = 'backups_offsite';

    /** Cache key held while a backup or restore runs, so the buttons can say so. */
    public const BUSY_KEY = 'backup.busy';

    public static function encryptionReady(): bool
    {
        return filled(config('backup.backup.password'));
    }

    public static function offsiteConfigured(): bool
    {
        return in_array(self::OFFSITE_DISK, (array) config('backup.backup.destination.disks'), true);
    }

    public static function busy(): ?string
    {
        $v = Cache::get(self::BUSY_KEY);

        return is_string($v) ? $v : null;
    }

    /**
     * Backups on this server, newest first, with what is inside each zip.
     *
     * @return list<array{file: string, date: string, iso: string, size: string, db: string, files: int, encrypted: bool, offsite: bool}>
     */
    public static function all(): array
    {
        try {
            $backups = BackupDestination::create(self::DISK, self::NAME)->backups();
        } catch (Throwable) {
            return [];
        }
        $offsite = self::offsiteFiles();

        $out = [];
        foreach ($backups as $b) {
            $file = basename($b->path());
            $inside = self::inspect(Storage::disk(self::DISK)->path($b->path()));

            $out[] = [
                'file' => $file,
                'date' => $b->date()->timezone(config('app.timezone'))->format('M j, Y · g:i A'),
                'iso' => $b->date()->toIso8601String(),
                'size' => self::human($b->sizeInBytes()),
                'db' => $inside['db_bytes'] === null ? '—' : self::human($inside['db_bytes']),
                'files' => $inside['files'],
                'encrypted' => $inside['encrypted'],
                'offsite' => isset($offsite[$file]) && $offsite[$file] === (int) $b->sizeInBytes(),
            ];
        }

        return $out;
    }

    /** Path of a server backup by its file name, or null when there is no such backup. */
    public static function path(string $file): ?string
    {
        if (! preg_match('/^[\w.-]+\.zip$/', $file)) {
            return null;
        }
        $relative = self::NAME.'/'.$file;

        return Storage::disk(self::DISK)->exists($relative) ? Storage::disk(self::DISK)->path($relative) : null;
    }

    /**
     * What a backup zip holds. Names and sizes are readable without the password.
     *
     * @return array{db_bytes: ?int, db_entry: ?string, files: int, encrypted: bool}
     */
    public static function inspect(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return ['db_bytes' => null, 'db_entry' => null, 'files' => 0, 'encrypted' => false];
        }
        $db = null;
        $entry = null;
        $files = 0;
        $encrypted = $zip->numFiles > 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $s = $zip->statIndex($i);
            if ($s === false || str_ends_with($s['name'], '/')) {
                continue;
            }
            $name = str_replace('\\', '/', $s['name']);
            $encrypted = $encrypted && $s['encryption_method'] !== ZipArchive::EM_NONE;
            if (str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                $db = (int) $s['size'];
                $entry = $s['name'];
            } elseif (str_starts_with($name, 'research/')) {
                $files++;
            }
        }
        $zip->close();

        return ['db_bytes' => $db, 'db_entry' => $entry, 'files' => $files, 'encrypted' => $encrypted];
    }

    /** @return array<string, int> file name => size on the off-server copy */
    private static function offsiteFiles(): array
    {
        if (! self::offsiteConfigured()) {
            return [];
        }
        try {
            $disk = Storage::disk(self::OFFSITE_DISK);

            return collect($disk->files(self::NAME))->mapWithKeys(fn (string $f) => [basename($f) => (int) $disk->size($f)])->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array{when: string, error: string}> recent failed backups and restores, from the activity log */
    public static function failures(int $limit = 5): array
    {
        return Activity::where('event', 'backup')->latest('id')->limit(40)->get()
            ->filter(fn (Activity $a) => ($a->properties['details']['result'] ?? null) === 'failed')
            ->take($limit)
            ->map(fn (Activity $a) => [
                'when' => $a->created_at?->format('M j, Y · g:i A') ?? '',
                'error' => (string) $a->description,
            ])->values()->all();
    }

    /** @return array<string, mixed>|null the last restore to staging (manual or the monthly test) */
    public static function lastRestore(): ?array
    {
        $v = Setting::valueOf('backup.last_restore');

        return is_array($v) ? $v : null;
    }

    /** @return array{when: ?string, ok: bool}|null the newest backup event, for System health */
    public static function lastRun(): ?array
    {
        $a = Activity::where('event', 'backup')->where('description', 'like', 'Backup %')->latest('id')->first();

        return $a === null ? null : [
            'when' => $a->created_at?->format('M j · g:i A'),
            'ok' => ($a->properties['details']['result'] ?? 'ok') !== 'failed',
        ];
    }

    /** Emails every active System administrator (§12: "Backup failed"). */
    public static function alertAdmins(string $subject, string $body): void
    {
        $admins = User::role(User::ROLE_SYSADMIN)->where('is_active', true)->whereNull('deactivated_at')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new WorkflowNotice($subject, $body, route('admin.backups')));
        }
    }

    public static function human(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $n : number_format($n, $n < 10 ? 1 : 0)).' '.$units[$i];
    }

    public static function formatDate(?string $iso): string
    {
        return $iso ? Carbon::parse($iso)->timezone(config('app.timezone'))->format('M j, Y · g:i A') : '—';
    }
}
