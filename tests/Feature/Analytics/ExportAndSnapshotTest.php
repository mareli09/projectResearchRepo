<?php

use App\Domain\Analytics\Dashboard;
use App\Domain\Analytics\DashboardExport;
use App\Domain\Analytics\KpiSnapshots;
use App\Domain\Audit\Activity;
use App\Models\AcademicUnit;
use App\Models\KpiSnapshot;
use App\Models\ResearchCluster;
use App\Models\ResearchRecord;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/* M7 dashboards (CLAUDE.md §7 M7): nightly KPI snapshots, real usage and screening, Excel/CSV/PDF exports. */
beforeEach(function () {
    seedBasics();
    Cache::flush();
    $this->urdc = userWithRole(User::ROLE_URDC);
    foreach ([2024, 2025, 2025] as $i => $year) {
        makeRecord(['year' => $year, 'keywords' => '=cmd injection, flood risk, prediction'])
            ->forceFill(['status' => 'published', 'published_at' => now(), 'downloads' => 10 * ($i + 1)])->save();
    }
});

it('counts the summary figures into kpi_snapshots and the dashboard reads them', function () {
    $this->artisan('analytics:snapshot')->assertSuccessful();

    $ceis = AcademicUnit::where('code', 'CEIS')->value('id');
    expect(KpiSnapshot::where(['scope_type' => 'college', 'scope_id' => $ceis, 'metric' => 'outputs', 'period' => 'all'])->value('value'))->toEqual(3)
        ->and(KpiSnapshot::where(['scope_type' => 'college', 'scope_id' => $ceis, 'metric' => 'outputs', 'period' => '2025'])->value('value'))->toEqual(2);

    // A record published after the snapshot is not in the cards until the next night.
    makeRecord(['year' => 2025])->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $d = Dashboard::build(['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], [], true);
    expect($d['kpis'][0][1])->toBe('3')->and($d['kpis'][4][1])->toBe('60')->and($d['asOf'])->not->toBeNull();

    // Theme and SDG filters are counted live.
    $live = Dashboard::build(['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], ['sdg' => 11], true);
    expect($live['kpis'][0][1])->toBe('4')->and($live['asOf'])->toBeNull();

    $this->actingAs($this->urdc)->get('/urdc/analytics')->assertOk()->assertSee('Summary figures as of')->assertDontSee('data-preview', false)->assertDontSee('(sample)')->assertDontSee('AY 2026–2027 · sample');
});

it('never shows another cluster’s figures to a coordinator', function () {
    $this->artisan('analytics:snapshot')->assertSuccessful();
    $coord = userWithRole(User::ROLE_COORDINATOR);
    $cluster = ResearchCluster::whereHas('academicUnits', fn ($q) => $q->where('code', 'CEIS'))->firstOrFail();
    $cluster->update(['coordinator_user_id' => $coord->id]);

    expect(KpiSnapshots::summary(['CEIS'])['outputs'])->toBe(3);
    $d = Dashboard::build($cluster->academicUnits->pluck('code')->all(), ['college' => 'CMT'], false);
    expect($d['kpis'][0][1])->toBe('0');

    $this->actingAs($coord)->get('/urdc/analytics/export/csv')->assertForbidden();
    $this->actingAs($coord)->get('/coordinator/analytics/export/csv')->assertOk();
});

it('counts active users, views and screening from the logs', function () {
    $student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => AcademicUnit::where('code', 'CEIS')->value('id')]);
    $record = ResearchRecord::published()->firstOrFail();
    $this->actingAs($student)->get('/studies/'.$record->public_id)->assertOk();

    $d = Dashboard::build(['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], [], true);
    expect($d['kpis'][3][1])->toBe('1')
        ->and(array_sum($d['charts']['usage']['series'][0]['data']))->toBe(1)
        ->and($d['screeningTotals']['screened'])->toBe(0);
});

it('exports Excel, CSV and PDF with the page filters, and logs each export', function () {
    $xlsx = $this->actingAs($this->urdc)->get('/urdc/analytics/export/xlsx?year=2025')->assertOk();
    expect($xlsx->headers->get('content-disposition'))->toContain('university-research-dashboard-')->toContain('.xlsx');

    $csv = $this->actingAs($this->urdc)->get('/urdc/analytics/export/csv?year=2025')->assertOk();
    $body = $csv->streamedContent();
    expect($body)->toStartWith("\xEF\xBB\xBF")->toContain('Year: 2025')->toContain('Research output per year')
        ->toContain('Submission compliance per unit')->toContain("'=cmd injection")->not->toContain(',=cmd');

    $pdf = $this->actingAs($this->urdc)->get('/urdc/analytics/export/pdf')->assertOk();
    expect(substr((string) $pdf->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs($this->urdc)->get('/urdc/analytics/export/docx')->assertNotFound();
    expect(Activity::where('event', 'download')->where('description', 'like', 'Dashboard exported%')->count())->toBe(3);
});

it('turns every panel into a table', function () {
    $tables = collect(DashboardExport::tables(Dashboard::build(['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'], [], true), true))->keyBy('title');

    expect($tables->keys()->all())->toContain('Summary', 'Research output per year', 'Outputs by college and type', 'Theme distribution',
        'Themes by college', 'SDG alignment', 'Top keywords', 'Emerging keywords', 'Adviser load', 'Similarity screening', 'Submission compliance per unit',
        'Repository usage', 'Most accessed studies', 'Searches with no or few results')
        ->and($tables['Outputs by college and type']['headers'])->toBe(['Research type', 'CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'])
        ->and($tables['Research output per year']['note'])->toContain('descriptive only');

    // One college in scope: visual 3 lists its programs instead.
    $one = collect(DashboardExport::tables(Dashboard::build(['CEIS'], [], true), true))->keyBy('title');
    expect($one['Outputs by program and type']['headers'])->toContain('BS Information Technology');
});
