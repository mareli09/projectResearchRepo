<?php

namespace App\Domain\Analytics;

use App\Domain\Audit\Activity;
use App\Domain\Repository\ResearchTypes;
use App\Domain\Search\UsageLog;
use App\Domain\Site\Corpus;
use App\Models\AcademicUnit;
use App\Models\ResearchRecord;
use App\Models\Sdg;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Table 7 visual analytics (CLAUDE.md §7 M7) as ECharts options.
 * The summary cards read the nightly kpi_snapshots when the filters allow it (college and
 * year); theme and SDG filters, and a repository with no snapshot yet, are counted live.
 * Charts come from the published records (cached corpus), the screening tables, the
 * activity log (views), search_logs and download_logs.
 */
final class Dashboard
{
    /**
     * @param  list<string>  $scope  college codes in scope
     * @param  array<string, mixed>  $filters  college, year, theme, sdg
     * @return array<string, mixed>
     */
    public static function build(array $scope, array $filters, bool $full): array
    {
        $base = Corpus::records()->filter(fn ($r) => in_array($r['college'], $scope, true));
        if (! empty($filters['college'])) {
            $base = $base->where('college', $filters['college']);
        }
        if (! empty($filters['theme'])) {
            $base = $base->where('theme', (int) $filters['theme']);
        }
        if (! empty($filters['sdg'])) {
            $sdg = (int) $filters['sdg'];
            $base = $base->filter(fn ($r) => in_array($sdg, (array) $r['sdgs'], true));
        }
        $recs = empty($filters['year']) ? $base : $base->where('year', (int) $filters['year']);
        // Re-index as plain lists for the chart builders.
        $base = collect(array_values($base->all()));
        $recs = collect(array_values($recs->all()));

        $cols = array_values(array_filter(['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], fn ($c) => in_array($c, $scope, true)));
        // A college outside the scope (for example another cluster's) gives an empty scope, never its figures.
        $inScope = ! empty($filters['college']) ? array_values(array_intersect($cols, [(string) $filters['college']])) : $cols;
        $whole = count($inScope) >= 6;
        $labels = Corpus::themeLabels();
        $sdgNames = Sdg::pluck('name', 'id')->all();

        // Summary cards: from the nightly snapshot when only college and year are filtered.
        $snap = empty($filters['theme']) && empty($filters['sdg'])
            ? KpiSnapshots::summary($inScope, empty($filters['year']) ? null : (int) $filters['year']) : null;
        if ($snap !== null) {
            [$total, $latestYear, $thisYr, $lastYr, $active, $downloads] = [$snap['outputs'], $snap['latest_year'], $snap['this_year'], $snap['last_year'], $snap['active_users'], $snap['downloads']];
        } else {
            $latestYear = (int) ($base->max('year') ?? date('Y'));
            [$total, $thisYr, $lastYr, $downloads] = [$recs->count(), $base->where('year', $latestYear)->count(), $base->where('year', $latestYear - 1)->count(), (int) $recs->sum('downloads')];
            $active = self::activeUsers($inScope, $whole);
        }
        $screening = self::screening($inScope);

        return [
            'kpis' => [
                ['Total outputs', number_format($total), 'in the repository', ''],
                ["Outputs in {$latestYear}", number_format($thisYr), 'calendar year', ''],
                ['Growth rate', ($lastYr ? number_format(($thisYr - $lastYr) / $lastYr * 100, 1) : '0').'%', "{$latestYear} vs ".($latestYear - 1), $thisYr >= $lastYr ? 'up' : ''],
                ['Active users', number_format($active), 'last '.KpiSnapshots::ACTIVE_DAYS.' days', ''],
                ['Downloads', number_format($downloads), 'all time', ''],
            ],
            'asOf' => $snap !== null ? $snap['computed_at']->format('M j, Y · g:i A') : null,
            'screeningTotals' => $screening['totals'],
            'charts' => array_filter([
                'trend' => self::trend($base->all()),
                'college' => self::byCollegeAndType($recs->all(), $cols),
                'scatter' => self::themeMap($recs->all(), $labels),
                'themes' => self::themeBars($recs->all(), $labels),
                'heat' => $full ? self::heat($recs->all(), $cols, $labels) : null,
                'type' => self::typeDonut($recs->all()),
                'sdg' => self::sdgBars($recs->all(), $sdgNames),
                'keywords' => self::keywordBars($recs->all()),
                'advisers' => self::adviserBars($recs->all()),
                'screening' => $screening['chart'],
                'usage' => $full ? self::usage($inScope, $whole) : null,
            ]),
            'compliance' => collect($cols)->map(function ($c) use ($recs) {
                $all = $recs->where('college', $c);
                $ok = $all->where('complete', true)->count();

                return ['college' => $c, 'expected' => $all->count(), 'complete' => $ok, 'rate' => $all->count() ? $ok / $all->count() * 100 : 0];
            })->all(),
            'topAccessed' => $recs->sortByDesc('views')->take(6)->values()->all(),
            'searchGaps' => UsageLog::gaps(),
            'count' => $recs->count(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function trend(array $rows): array
    {
        $base = collect($rows);
        $years = range(2015, (int) ($base->max('year') ?? 2025));
        $counts = $base->countBy('year');
        $ys = array_map(fn ($y) => (int) ($counts[$y] ?? 0), $years);
        // Linear trend by least squares: descriptive only, never a forecast used for decisions (§14 Safety).
        $n = count($years);
        $mx = ($n - 1) / 2;
        $my = array_sum($ys) / max(1, $n);
        $num = $den = 0.0;
        foreach ($ys as $i => $y) {
            $num += ($i - $mx) * ($y - $my);
            $den += ($i - $mx) ** 2;
        }
        $slope = $den ? $num / $den : 0;

        return ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [],
            'xAxis' => ['type' => 'category', 'data' => $years], 'yAxis' => ['type' => 'value'],
            'series' => [
                ['name' => 'Outputs', 'type' => 'line', 'data' => $ys, 'smooth' => .2, 'symbolSize' => 7, 'areaStyle' => ['opacity' => .12], 'lineStyle' => ['width' => 2.5], 'itemStyle' => ['color' => 'var(--c1)']],
                ['name' => 'Trend (descriptive only)', 'type' => 'line', 'data' => array_map(fn ($i) => round($my + $slope * ($i - $mx), 1), array_keys($ys)), 'symbol' => 'none', 'lineStyle' => ['type' => 'dashed', 'color' => 'var(--c2)', 'width' => 1.5], 'itemStyle' => ['color' => 'var(--c2)']],
            ]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $cols
     * @return array<string, mixed>
     */
    private static function byCollegeAndType(array $rows, array $cols): array
    {
        $recs = collect($rows);

        return ['tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']], 'legend' => (object) [],
            'xAxis' => ['type' => 'category', 'data' => $cols], 'yAxis' => ['type' => 'value'],
            'series' => collect(ResearchTypes::TYPES)->map(fn ($label, $key) => [
                'name' => $label, 'type' => 'bar', 'stack' => 'a', 'barMaxWidth' => 40,
                'data' => array_map(fn ($c) => $recs->where('college', $c)->where('type', $key)->count(), $cols),
            ])->values()->all()];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    private static function themeMap(array $rows, array $labels): array
    {
        $recs = collect($rows);

        return ['tooltip' => ['trigger' => 'item'], 'legend' => (object) [],
            'xAxis' => ['type' => 'value', 'axisLabel' => ['show' => false], 'splitLine' => ['show' => false]],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['show' => false], 'splitLine' => ['show' => false]],
            'series' => collect($labels)->map(fn ($label, $id) => [
                'name' => $label, 'type' => 'scatter', 'symbolSize' => 5, 'itemStyle' => ['opacity' => .7],
                'data' => $recs->where('theme', $id)->map(fn ($r) => [$r['x'], $r['y'], $r['public_id']])->values()->all(),
            ])->values()->all()];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    private static function themeBars(array $rows, array $labels): array
    {
        $recs = collect($rows);
        $counts = $recs->countBy('theme');
        $palette = ['var(--c1)', 'var(--c2)', 'var(--c3)', 'var(--c4)', 'var(--c5)', 'var(--c6)', 'var(--c7)'];

        return ['tooltip' => (object) [], 'xAxis' => ['type' => 'value'],
            'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => array_values($labels), 'axisLabel' => ['width' => 150, 'overflow' => 'break']],
            'series' => [['type' => 'bar', 'barMaxWidth' => 22, 'label' => ['show' => true, 'position' => 'right', 'color' => 'var(--muted)'],
                'data' => collect($labels)->map(fn ($l, $id) => ['value' => (int) ($counts[$id] ?? 0), 'key' => $id, 'itemStyle' => ['color' => $palette[($id - 1) % 7]]])->values()->all()]]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $cols
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    private static function heat(array $rows, array $cols, array $labels): array
    {
        $recs = collect($rows);
        $data = [];
        $max = 0;
        foreach (array_keys($labels) as $i => $id) {
            foreach ($cols as $j => $c) {
                $v = $recs->where('theme', $id)->where('college', $c)->count();
                $max = max($max, $v);
                $data[] = [$j, $i, $v];
            }
        }

        return ['tooltip' => (object) [], 'grid' => ['left' => 8, 'right' => 16, 'top' => 10, 'bottom' => 50, 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'data' => $cols], 'yAxis' => ['type' => 'category', 'data' => array_values($labels), 'inverse' => true],
            'visualMap' => ['min' => 0, 'max' => $max, 'orient' => 'horizontal', 'left' => 'center', 'bottom' => 0, 'itemWidth' => 12, 'itemHeight' => 120, 'inRange' => ['color' => ['var(--heat0)', 'var(--heat1)']], 'textStyle' => ['color' => 'var(--muted)']],
            'series' => [['type' => 'heatmap', 'data' => $data, 'label' => ['show' => true, 'color' => 'var(--ink)', 'fontSize' => 11, 'textBorderColor' => 'var(--surface)', 'textBorderWidth' => 3], 'itemStyle' => ['borderColor' => 'var(--surface)', 'borderWidth' => 2]]]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function typeDonut(array $rows): array
    {
        $recs = collect($rows);
        $counts = $recs->countBy('type');

        return ['tooltip' => (object) [], 'legend' => ['bottom' => 0, 'top' => 'auto'],
            'series' => [['type' => 'pie', 'radius' => ['48%', '72%'], 'center' => ['50%', '45%'], 'itemStyle' => ['borderColor' => 'var(--surface)', 'borderWidth' => 2], 'label' => ['color' => 'var(--muted)', 'formatter' => '{d}%'],
                'data' => collect(ResearchTypes::TYPES)->map(fn ($label, $key) => ['name' => $label, 'value' => (int) ($counts[$key] ?? 0), 'key' => $key])->values()->all()]]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private static function sdgBars(array $rows, array $names): array
    {
        $recs = collect($rows);
        $counts = self::values($recs, 'sdgs')->countBy()->sortDesc();

        return ['tooltip' => (object) [], 'xAxis' => ['type' => 'value'],
            'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => $counts->keys()->map(fn ($k) => 'SDG '.$k)->all()],
            'series' => [['type' => 'bar', 'barMaxWidth' => 18, 'itemStyle' => ['color' => 'var(--c3)'],
                'data' => $counts->map(fn ($v, $k) => ['value' => $v, 'key' => $k, 'name' => 'SDG '.$k.' · '.($names[$k] ?? '')])->values()->all()]]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function keywordBars(array $rows): array
    {
        $recs = collect($rows);
        $min = (int) ($recs->max('year') ?? 2025) - 2;
        $counts = self::values($recs->where('year', '>=', $min), 'keywords')->countBy()->sortDesc()->take(10);

        return ['tooltip' => (object) [], 'xAxis' => ['type' => 'value'], 'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => $counts->keys()->all()],
            'series' => [['type' => 'bar', 'barMaxWidth' => 16, 'itemStyle' => ['color' => 'var(--c2)'], 'data' => $counts->values()->all()]]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function adviserBars(array $rows): array
    {
        $recs = collect($rows);
        $min = (int) ($recs->max('year') ?? 2025) - 2;
        $counts = $recs->where('year', '>=', $min)->countBy('adviser')->sortDesc()->take(8);

        return ['tooltip' => (object) [], 'xAxis' => ['type' => 'value'], 'yAxis' => ['type' => 'category', 'inverse' => true, 'data' => $counts->keys()->all()],
            'series' => [['type' => 'bar', 'barMaxWidth' => 16, 'itemStyle' => ['color' => 'var(--c5)'], 'data' => $counts->values()->all()]]];
    }

    /**
     * Distinct users who did something logged in the last 30 days, in the colleges in scope.
     *
     * @param  list<string>  $colleges
     */
    private static function activeUsers(array $colleges, bool $whole): int
    {
        $q = Activity::query()->where('activity_log.causer_type', (new User)->getMorphClass())
            ->where('activity_log.created_at', '>=', now()->subDays(KpiSnapshots::ACTIVE_DAYS));
        if (! $whole) {
            $q->join('users', 'users.id', '=', 'activity_log.causer_id')
                ->whereIn('users.academic_unit_id', AcademicUnit::whereIn('code', $colleges)->pluck('id'));
        }

        return (int) $q->distinct()->count('activity_log.causer_id');
    }

    /**
     * Similarity screening (Table 7 visual 10): checks in the last 6 months by flag, and the totals
     * for the academic year (June to May): screened, flagged, and duplicates confirmed by a coordinator.
     *
     * @param  list<string>  $colleges
     * @return array{chart: array<string, mixed>, totals: array{screened: int, flagged: int, confirmed: int, year: string}}
     */
    private static function screening(array $colleges): array
    {
        $units = AcademicUnit::whereIn('code', $colleges)->pluck('id');
        $tz = (string) config('app.timezone');
        $now = now($tz);
        $months = collect(range(5, 0))->map(fn (int $i) => $now->copy()->startOfMonth()->subMonths($i));
        $checks = DB::table('similarity_checks')->join('submissions', 'submissions.id', '=', 'similarity_checks.submission_id')
            ->whereIn('submissions.academic_unit_id', $units)
            ->where('similarity_checks.run_at', '>=', $months->first()->copy()->utc())
            ->get(['similarity_checks.run_at', 'similarity_checks.flag']);
        $count = fn (string $flag) => $months->map(fn (Carbon $m) => $checks->filter(fn ($c) => $c->flag === $flag
            && Carbon::parse($c->run_at, 'UTC')->timezone($tz)->format('Y-m') === $m->format('Y-m'))->count())->all();

        $ayStart = $now->copy()->setDate($now->month >= 6 ? $now->year : $now->year - 1, 6, 1)->startOfDay();
        $ay = DB::table('similarity_checks')->join('submissions', 'submissions.id', '=', 'similarity_checks.submission_id')
            ->whereIn('submissions.academic_unit_id', $units)->where('similarity_checks.run_at', '>=', $ayStart->copy()->utc());
        $confirmed = DB::table('screening_decisions')->join('submissions', 'submissions.id', '=', 'screening_decisions.submission_id')
            ->whereIn('submissions.academic_unit_id', $units)->where('screening_decisions.label_for_calibration', 'duplicate')
            ->where('screening_decisions.created_at', '>=', $ayStart->copy()->utc())->count();

        return [
            'chart' => ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [],
                'xAxis' => ['type' => 'category', 'data' => $months->map(fn (Carbon $m) => $m->format('M'))->all()], 'yAxis' => ['type' => 'value', 'minInterval' => 1],
                'series' => [
                    ['name' => 'Original', 'type' => 'bar', 'stack' => 's', 'data' => $count('original'), 'itemStyle' => ['color' => 'var(--ok)']],
                    ['name' => 'Needs review', 'type' => 'bar', 'stack' => 's', 'data' => $count('similar'), 'itemStyle' => ['color' => 'var(--warn)']],
                    ['name' => 'Probable duplicate', 'type' => 'bar', 'stack' => 's', 'data' => $count('probable_duplicate'), 'itemStyle' => ['color' => 'var(--bad)']],
                ]],
            'totals' => [
                'screened' => (clone $ay)->count(),
                'flagged' => (clone $ay)->where('similarity_checks.flag', '!=', 'original')->count(),
                'confirmed' => $confirmed,
                'year' => 'AY '.$ayStart->year.'–'.($ayStart->year + 1),
            ],
        ];
    }

    /**
     * Repository usage (Table 7 visual 12): study views, searches and downloads per week, last 12 weeks.
     * Searches are counted for the whole university (a search belongs to no college).
     *
     * @param  list<string>  $colleges
     * @return array<string, mixed>
     */
    private static function usage(array $colleges, bool $whole): array
    {
        $tz = (string) config('app.timezone');
        $now = now($tz);
        $weeks = collect(range(11, 0))->map(fn (int $i) => $now->copy()->startOfWeek()->subWeeks($i));
        $from = $weeks->first()->copy()->utc();
        $units = AcademicUnit::whereIn('code', $colleges)->pluck('id');

        // Timestamps are stored in UTC; bucket them into Manila weeks.
        $times = fn ($q, string $col) => $q->where($col, '>=', $from)->pluck($col)
            ->map(fn ($t) => Carbon::parse((string) $t, 'UTC')->timezone($tz));
        $views = $times(DB::table('activity_log')->where('event', 'view')->where('subject_type', (new ResearchRecord)->getMorphClass())
            ->join('research_records', 'research_records.id', '=', 'activity_log.subject_id')
            ->when(! $whole, fn ($q) => $q->whereIn('research_records.academic_unit_id', $units)), 'activity_log.created_at');
        $downloads = $times(DB::table('download_logs')->join('research_records', 'research_records.id', '=', 'download_logs.record_id')
            ->when(! $whole, fn ($q) => $q->whereIn('research_records.academic_unit_id', $units)), 'download_logs.created_at');
        $searches = $times(DB::table('search_logs'), 'search_logs.created_at');

        $byWeek = fn (Collection $at) => $weeks->map(fn (Carbon $w) => $at->filter(fn (Carbon $t) => $t->between($w, $w->copy()->endOfWeek()))->count())->all();

        return ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [],
            'xAxis' => ['type' => 'category', 'data' => $weeks->map(fn (Carbon $w) => $w->format('M j'))->all()], 'yAxis' => ['type' => 'value', 'minInterval' => 1],
            'series' => [
                ['name' => 'Views', 'type' => 'line', 'smooth' => .2, 'data' => $byWeek($views)],
                ['name' => 'Searches', 'type' => 'line', 'smooth' => .2, 'data' => $byWeek($searches)],
                ['name' => 'Downloads', 'type' => 'bar', 'barMaxWidth' => 14, 'data' => $byWeek($downloads)],
            ]];
    }

    /**
     * All values of a list field (keywords, sdgs) across records.
     *
     * @param  Collection<int, array<string, mixed>>  $recs
     * @return Collection<int, int|string>
     */
    public static function values(Collection $recs, string $key): Collection
    {
        $out = [];
        foreach ($recs as $r) {
            foreach ((array) $r[$key] as $v) {
                $out[] = $v;
            }
        }

        return collect($out);
    }
}
