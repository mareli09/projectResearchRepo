<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\DownloadLog;
use App\Models\RecordFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads from the private research disk (CLAUDE.md §1 rule 9, §14 Security):
 * a signed link that expires, a permission check on every request, and a log entry.
 */
class RecordFileController extends Controller
{
    public static function signedUrl(RecordFile $file): string
    {
        return URL::temporarySignedRoute('files.download', now()->addMinutes(10), ['file' => $file->id]);
    }

    public function download(Request $request, RecordFile $file): StreamedResponse
    {
        Gate::authorize('download', $file);
        abort_unless(Storage::disk('research')->exists($file->path), 404, 'The file is missing from storage.');

        DownloadLog::create([
            'user_id' => $request->user()?->id,
            'record_id' => $file->record_id,
            'record_file_id' => $file->id,
            'slot' => $file->slot,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
        $file->record?->increment('downloads');
        Audit::record(AuditEvent::DOWNLOAD, 'Downloaded '.str_replace('_', ' ', $file->slot), $file->record, [
            'file_id' => $file->id,
            'slot' => $file->slot,
        ]);

        return Storage::disk('research')->download($file->path, $file->original_name);
    }
}
