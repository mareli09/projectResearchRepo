<?php

namespace App\Domain\Analytics;

use App\Domain\Audit\Activity;
use App\Models\AcademicUnit;
use App\Models\KpiSnapshot;
use App\Models\ResearchRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nightly KPI snapshots (CLAUDE.md §7 M7 "Speed"): the summary figures of every dashboard are
 * counted once a night into kpi_snapshots, so the dashboards read a few rows instead of
 * counting the whole repository on each visit. Figures are stored per college and per year
 * ("all" for every year); a cluster or the university is the sum of its colleges. Active users
 * and searches are also stored for the whole university, which includes staff with no college.
 */
final class KpiSnapshots
{
    /** Figures counted per college and year. */
    public const RECORD_METRICS = ['outputs', 'downloads', 'views', 'complete'];

    public const ACTIVE_DAYS = 30;

    /** Counts everything again and replaces the stored snapshot. Returns the number of rows written. */
    public static function refresh(): int
    {
        $now = now();
        $rows = [];
        $add = function (string $type, ?int $id, string $metric, string $period, float|int $value) use (&$rows, $now): void {
            $rows[] = ['scope_type' => $type, 'scope_id' => $id, 'metric' => $metric, 'period' => $period, 'value' => $value, 'computed_at' => $now];
        };

        // Published records per college and year.
        $byYear = ResearchRecord::published()
            ->selectRaw('academic_unit_id, year, COUNT(*) AS outputs, SUM(downloads) AS downloads, SUM(views) AS views, SUM(is_complete) AS complete')
            ->groupBy('academic_unit_id', 'year')->get();
        foreach ($byYear->groupBy('academic_unit_id') as $unitId => $years) {
            foreach (self::RECORD_METRICS as $m) {
                $add('college', (int) $unitId, $m, 'all', (int) $years->sum($m));
                foreach ($years as $y) {
                    $add('college', (int) $unitId, $m, (string) $y->year, (int) $y->getAttribute($m));
                }
            }
        }

        // Active users: anyone who did something logged in the last 30 days.
        $since = $now->copy()->subDays(self::ACTIVE_DAYS);
        $active = Activity::query()->where('causer_type', (new User)->getMorphClass())->where('created_at', '>=', $since)->distinct()->pluck('causer_id');
        $add('university', null, 'active_users', 'last_30_days', $active->count());
        foreach (User::whereIn('id', $active)->whereNotNull('academic_unit_id')->selectRaw('academic_unit_id, COUNT(*) AS n')->groupBy('academic_unit_id')->get() as $u) {
            $add('college', (int) $u->academic_unit_id, 'active_users', 'last_30_days', (int) $u->getAttribute('n'));
        }
        $add('university', null, 'searches', 'last_30_days', DB::table('search_logs')->where('created_at', '>=', $since)->count());

        DB::transaction(function () use ($rows): void {
            KpiSnapshot::query()->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                KpiSnapshot::insert($chunk);
            }
        });

        return count($rows);
    }

    public static function computedAt(): ?Carbon
    {
        $at = KpiSnapshot::max('computed_at');

        return $at ? Carbon::parse($at)->timezone(config('app.timezone')) : null;
    }

    /**
     * The summary figures of a dashboard, or null when there is no snapshot yet.
     *
     * @param  list<string>  $colleges  college codes in scope (all six = the whole university)
     * @return array{outputs: int, downloads: int, views: int, complete: int, active_users: int, this_year: int, last_year: int, latest_year: int, computed_at: Carbon}|null
     */
    public static function summary(array $colleges, ?int $year = null): ?array
    {
        $at = self::computedAt();
        if ($at === null) {
            return null;
        }
        $ids = AcademicUnit::whereIn('code', $colleges)->pluck('id')->all();
        $whole = count($colleges) >= AcademicUnit::count();
        $sum = fn (string $metric, string $period) => (int) KpiSnapshot::where('scope_type', 'college')->whereIn('scope_id', $ids)
            ->where('metric', $metric)->where('period', $period)->sum('value');

        $latest = (int) (KpiSnapshot::where('scope_type', 'college')->whereIn('scope_id', $ids)->where('metric', 'outputs')
            ->where('period', '!=', 'all')->where('value', '>', 0)->max(DB::raw('CAST(period AS UNSIGNED)')) ?: date('Y'));
        $period = $year === null ? 'all' : (string) $year;

        return [
            'outputs' => $sum('outputs', $period),
            'downloads' => $sum('downloads', $period),
            'views' => $sum('views', $period),
            'complete' => $sum('complete', $period),
            'active_users' => $whole
                ? (int) KpiSnapshot::where('scope_type', 'university')->where('metric', 'active_users')->value('value')
                : $sum('active_users', 'last_30_days'),
            'this_year' => $sum('outputs', (string) $latest),
            'last_year' => $sum('outputs', (string) ($latest - 1)),
            'latest_year' => $latest,
            'computed_at' => $at,
        ];
    }
}
