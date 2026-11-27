<?php

namespace App\Domain\Search;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\ResearchRecord;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Search and view logging (CLAUDE.md §7 M4, §13): every search goes to search_logs, including
 * searches with no results (Table 7 visual 13), and every study view is counted and logged.
 */
final class UsageLog
{
    /** @param array<string, mixed> $filters */
    public static function search(?User $user, array $filters, int $results, int $page = 1): void
    {
        $query = trim((string) ($filters['q'] ?? ''));
        $facets = array_diff_key($filters, ['q' => 1, 'sort' => 1]);
        // Only real searches: a query or a facet, first page only (paging is not a new search).
        if (($query === '' && $facets === []) || $page > 1) {
            return;
        }

        SearchLog::create([
            'user_id' => $user?->id,
            'query' => mb_substr($query, 0, 200),
            'filters' => $facets ?: null,
            'results_count' => $results,
            'created_at' => now(),
        ]);
        if ($user !== null) {
            Audit::record(AuditEvent::SEARCH, 'Searched'.($query !== '' ? " \"{$query}\"" : ' by filters'), null,
                ['query' => $query, 'filters' => $facets, 'results' => $results], $user);
        }
    }

    public static function view(ResearchRecord $record, ?User $user): void
    {
        // Count without touching updated_at, so the corpus cache is not invalidated by views.
        DB::table('research_records')->where('id', $record->id)->increment('views');
        Audit::record(AuditEvent::VIEW, "Viewed {$record->public_id}", $record, ['guest' => $user === null], $user);
    }

    /**
     * Searches that found nothing or almost nothing in the last 90 days (possible gaps in the collection).
     *
     * @return list<array{0: string, 1: int, 2: int, 3: string}> term, times searched, latest result count, last searched
     */
    public static function gaps(int $maxResults = 3, int $limit = 10): array
    {
        $rows = SearchLog::query()
            ->where('query', '!=', '')
            ->where('created_at', '>=', now()->subDays(90))
            ->where('results_count', '<=', $maxResults)
            ->selectRaw('LOWER(query) AS term, COUNT(*) AS times, MIN(results_count) AS results, MAX(created_at) AS last_at')
            ->groupByRaw('LOWER(query)')
            ->orderByDesc('times')->orderByDesc('last_at')
            ->limit($limit)->get();

        return $rows->map(fn ($r) => [
            (string) $r->getAttribute('term'), (int) $r->getAttribute('times'), (int) $r->getAttribute('results'),
            Carbon::parse((string) $r->getAttribute('last_at'))->format('M j, Y'),
        ])->values()->all();
    }
}
