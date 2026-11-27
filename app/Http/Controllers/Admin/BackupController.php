<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\BackupRestorer;
use App\Domain\Admin\Backups;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Jobs\RestoreBackupToStaging;
use App\Jobs\RunBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/** "Backups" for the System administrator (CLAUDE.md §7 M9). */
class BackupController extends Controller
{
    public function index(): View
    {
        try {
            $staging = BackupRestorer::stagingDatabase();
        } catch (Throwable $e) {
            $staging = null;
        }

        return view('admin.backups', [
            'backups' => Backups::all(),
            'encryptionReady' => Backups::encryptionReady(),
            'offsite' => Backups::offsiteConfigured(),
            'busy' => Backups::busy(),
            'failures' => Backups::failures(),
            'lastRestore' => Backups::lastRestore(),
            'staging' => $staging,
            'dailyAt' => (string) config('tua.backup.daily_at'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Backups::encryptionReady()) {
            return back()->withErrors(['backup' => 'Set BACKUP_ARCHIVE_PASSWORD in .env first. Backups are always encrypted.']);
        }
        if ($busy = Backups::busy()) {
            return back()->withErrors(['backup' => "Wait for the current task to finish ({$busy})."]);
        }

        Audit::record(AuditEvent::BACKUP, 'Backup started from the Backups page', null, ['result' => 'queued']);
        RunBackup::dispatch($request->user(), 'manual');

        return back()->with('status', 'The backup has started. It runs in the background; refresh this page in a few minutes.');
    }

    public function restore(Request $request, string $file): RedirectResponse
    {
        abort_if(Backups::path($file) === null, 404);
        if ($busy = Backups::busy()) {
            return back()->withErrors(['backup' => "Wait for the current task to finish ({$busy})."]);
        }

        Audit::record(AuditEvent::BACKUP, "Restore to staging started: {$file}", null, ['result' => 'queued', 'file' => $file]);
        RestoreBackupToStaging::dispatch($file, $request->user(), 'manual');

        return back()->with('status', "Restoring {$file} to the staging database. The check result appears below when it finishes.");
    }
}
