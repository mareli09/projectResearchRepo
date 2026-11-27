<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Dashboard;
use App\Domain\Search\RecordSearch;
use App\Domain\Search\UsageLog;
use App\Domain\Site\Corpus;
use App\Domain\Site\SiteContent;
use App\Models\AcademicUnit;
use App\Models\ResearchRecord;
use App\Models\Sdg;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public pages: landing page (CLAUDE.md §8), public catalog and study abstract (§7 M4). */
class SiteController extends Controller
{
    public function home(): View
    {
        $c = SiteContent::published();
        $corpus = Corpus::records();
        $pub = fn () => ResearchRecord::published()->with('academicUnit:id,code', 'authors');

        $featured = match ($c['featured']['mode']) {
            'viewed' => $pub()->orderByDesc('views')->limit($c['featured']['count'])->get(),
            'latest' => $pub()->orderByDesc('published_at')->limit($c['featured']['count'])->get(),
            default => $pub()->whereIn('public_id', $c['featured']['ids'])->get()->sortBy(fn ($r) => array_search($r->public_id, $c['featured']['ids'], true))->take($c['featured']['count']),
        };

        $clusterCounts = $corpus->countBy(fn ($r) => match ($r['college']) {
            'IBAM', 'CEIS' => 1, 'CMT', 'CAHS' => 2, 'SLCN' => 3, default => 4,
        });

        return view('site.home', [
            'c' => $c,
            'latest' => $pub()->orderByDesc('year')->orderByDesc('views')->limit(4)->get(),
            'featured' => $featured,
            'clusterCounts' => $clusterCounts,
            'sdgCounts' => Dashboard::values($corpus, 'sdgs')->countBy(),
            'sdgs' => Sdg::orderBy('id')->get(),
            'published' => $corpus->count(),
            'colleges' => AcademicUnit::count(),
            'units' => AcademicUnit::with('researchCluster')->orderBy('code')->get(),
            'collegeCounts' => $corpus->countBy('college'),
        ]);
    }

    public function catalog(Request $request): View
    {
        $filters = RecordSearch::filters($request);
        $results = RecordSearch::run($filters);
        UsageLog::search($request->user(), $filters, $results->total(), $results->currentPage());

        return view('library.search', [
            'public' => true,
            'filters' => $filters,
            'results' => $results,
            'facets' => RecordSearch::facets(),
        ]);
    }

    /** Guests see the title, abstract and metadata only. No files (§4). */
    public function catalogShow(Request $request, string $publicId): View
    {
        $record = ResearchRecord::published()->where('public_id', $publicId)
            ->with('authors', 'academicUnit', 'program', 'keywords', 'sdgs')->firstOrFail();
        UsageLog::view($record, $request->user());

        return view('library.study', [
            'record' => $record,
            'public' => true,
            'access' => null,
            'files' => collect(),
            'related' => LibraryController::related($record),
            'theme' => Corpus::records()->firstWhere('id', $record->id)['theme'] ?? null,
        ]);
    }
}
