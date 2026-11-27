<?php

namespace App\Domain\Analytics;

use App\Domain\Repository\ResearchTypes;
use App\Domain\Search\UsageLog;
use App\Domain\Site\Corpus;
use App\Models\Sdg;
use Illuminate\Support\Collection;

/**
 * Builds the Table 7 visual analytics (CLAUDE.md §7 M7) as ECharts options.
 * Counts come from the published records; screening, usage and active users are
 * sample figures until their modules log real data (Sprints 4, 5, 7).
 * Sprint 7 moves this onto nightly kpi_snapshots.
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

        $latestYear = (int) ($base->max('year') ?? date('Y'));
        $thisYr = $base->where('year', $latestYear)->count();
        $lastYr = $base->where('year', $latestYear - 1)->count();
        $cols = array_values(array_filter(['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], fn ($c) => in_array($c, $scope, true)));
        $labels = Corpus::themeLabels();
        $sdgNames = Sdg::pluck('name', 'id')->all();

        return [
            'kpis' => [
                ['Total outputs', number_format($recs->count()), 'in the repository', ''],
                ["Outputs in {$latestYear}", number_format($thisYr), 'calendar year', ''],
                ['Growth rate', ($lastYr ? number_format(($thisYr - $lastYr) / $lastYr * 100, 1) : '0').'%', "{$latestYear} vs ".($latestYear - 1), $thisYr >= $lastYr ? 'up' : ''],
                ['Active users', number_format((int) round(612 * max(1, $recs->count()) / 1419)), 'last 30 days (sample)', ''],
                ['Downloads', number_format($recs->sum('downloads')), 'all time', ''],
            ],
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
                'screening' => self::screening(),
                'usage' => $full ? self::usage() : null,
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
     * Sample until screening runs (Sprint 4).
     *
     * @return array<string, mixed>
     */
    private static function screening(): array
    {
        return ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [],
            'xAxis' => ['type' => 'category', 'data' => ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov']], 'yAxis' => ['type' => 'value'],
            'series' => [
                ['name' => 'Original', 'type' => 'bar', 'stack' => 's', 'data' => [18, 22, 31, 40, 29, 15], 'itemStyle' => ['color' => 'var(--ok)']],
                ['name' => 'Needs review', 'type' => 'bar', 'stack' => 's', 'data' => [3, 4, 6, 5, 4, 3], 'itemStyle' => ['color' => 'var(--warn)']],
                ['name' => 'Probable duplicate', 'type' => 'bar', 'stack' => 's', 'data' => [1, 0, 2, 1, 1, 1], 'itemStyle' => ['color' => 'var(--bad)']],
            ]];
    }

    /**
     * Sample until search_logs and download_logs fill (Sprints 2, 5).
     *
     * @return array<string, mixed>
     */
    private static function usage(): array
    {
        return ['tooltip' => ['trigger' => 'axis'], 'legend' => (object) [],
            'xAxis' => ['type' => 'category', 'data' => array_map(fn ($i) => 'W'.($i + 36), range(0, 11))], 'yAxis' => ['type' => 'value'],
            'series' => [
                ['name' => 'Views', 'type' => 'line', 'smooth' => .2, 'data' => [820, 910, 880, 1010, 1190, 1240, 1105, 1320, 1415, 1380, 1502, 1610]],
                ['name' => 'Searches', 'type' => 'line', 'smooth' => .2, 'data' => [540, 600, 610, 700, 820, 860, 790, 910, 990, 960, 1040, 1120]],
                ['name' => 'Downloads', 'type' => 'bar', 'barMaxWidth' => 14, 'data' => [120, 140, 130, 160, 190, 200, 180, 215, 240, 230, 250, 268]],
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
