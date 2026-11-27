<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Activity;
use App\Domain\Audit\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Activity log" (CLAUDE.md §7 M8): timestamped, filterable by event, user and date,
 * with CSV export. Read-only: no route edits or deletes entries.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('activity.index', [
            'entries' => $this->query($request)->with('causer')->paginate(50)->withQueryString(),
            'events' => AuditEvent::all(),
            'filters' => $request->only('event', 'user', 'from', 'to'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request)->with('causer');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly (§14)
            fputcsv($out, ['Time (Asia/Manila)', 'Event', 'User', 'Email', 'Description', 'Subject', 'IP address', 'Details']);
            $query->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $a) {
                    /** @var Activity $a */
                    $causer = $a->causer instanceof User ? $a->causer : null;
                    fputcsv($out, [
                        $a->created_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
                        $a->event,
                        $causer?->name,
                        $causer?->email,
                        $a->description,
                        $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '',
                        $a->properties->get('ip'),
                        json_encode(array_filter([
                            'details' => $a->properties->get('details'),
                            'old' => $a->properties->get('old'),
                            'new' => $a->properties->get('new'),
                        ])),
                    ]);
                }
            });
            fclose($out);
        }, 'activity-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<Activity> */
    private function query(Request $request): Builder
    {
        $request->validate([
            'event' => ['nullable', 'string'],
            'user' => ['nullable', 'string', 'max:190'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return Activity::query()
            ->where('log_name', 'audit')
            ->when($request->filled('event'), fn (Builder $q) => $q->where('event', $request->string('event')->toString()))
            ->when($request->filled('user'), function (Builder $q) use ($request): void {
                $ids = User::where('email', 'like', '%'.$request->string('user')->toString().'%')
                    ->orWhere('name', 'like', '%'.$request->string('user')->toString().'%')
                    ->pluck('id');
                $q->where('causer_type', (new User)->getMorphClass())->whereIn('causer_id', $ids);
            })
            ->when($request->filled('from'), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse((string) $request->input('from'))->startOfDay()))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse((string) $request->input('to'))->endOfDay()))
            ->latest('id');
    }
}
