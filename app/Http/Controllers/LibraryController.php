<?php

namespace App\Http\Controllers;

use App\Domain\Search\RecordSearch;
use App\Domain\Site\Corpus;
use App\Models\ResearchRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Signed-in library: search, study page, notifications (CLAUDE.md §7 M3, M4). */
class LibraryController extends Controller
{
    public function search(Request $request): View
    {
        $filters = RecordSearch::filters($request);

        return view('library.search', [
            'public' => false,
            'filters' => $filters,
            'results' => RecordSearch::run($filters),
            'facets' => RecordSearch::facets(),
        ]);
    }

    public function study(Request $request, string $publicId): View
    {
        $record = ResearchRecord::published()->where('public_id', $publicId)
            ->with('authors', 'academicUnit', 'program', 'keywords', 'sdgs', 'currentFiles')->firstOrFail();
        // The download policy reads $file->record; reuse the loaded record instead of querying it again.
        $record->currentFiles->each->setRelation('record', $record);

        return view('library.study', [
            'record' => $record,
            'public' => false,
            'files' => $record->currentFiles->keyBy('slot'),
            'related' => self::related($record),
            'theme' => Corpus::records()->firstWhere('id', $record->id)['theme'] ?? null,
        ]);
    }

    /**
     * Related studies. For now: most shared keywords, scored by overlap (Jaccard).
     * Sprint 4 replaces this with the top 5 by cosine similarity of the TF-IDF vectors.
     *
     * @return Collection<int, array{record: ResearchRecord, score: float}>
     */
    public static function related(ResearchRecord $record): Collection
    {
        $ids = $record->keywords->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = DB::table('record_keyword as rk')
            ->join('research_records as r', 'r.id', '=', 'rk.record_id')
            ->whereIn('rk.keyword_id', $ids)->where('rk.record_id', '!=', $record->id)
            ->where('r.status', 'published')->whereNull('r.deleted_at')
            ->groupBy('rk.record_id')->selectRaw('rk.record_id, COUNT(*) AS shared')
            ->orderByDesc('shared')->orderByDesc(DB::raw('MAX(r.views)'))->limit(5)->get();

        $models = ResearchRecord::with('academicUnit:id,code', 'keywords:id')->whereIn('id', $rows->pluck('record_id'))->get()->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $m = $models->get($row->record_id);
            if ($m === null) {
                continue;
            }
            $union = $ids->merge($m->keywords->pluck('id'))->unique()->count();
            $out[] = ['record' => $m, 'score' => $union ? round($row->shared / $union, 2) : 0.0];
        }

        return collect($out);
    }

    /** In-app copies of the emails the system sent this user (CLAUDE.md §7 M3). */
    public function notifications(Request $request): View
    {
        return view('library.notifications', [
            'items' => $request->user()->notifications()->paginate(20),
            'email' => $request->user()->email,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications are marked as read.');
    }
}
