<?php

use App\Domain\Analytics\Dashboard;
use App\Domain\Analytics\DashboardExport;
use App\Models\Program;
use App\Models\ResearchCluster;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/* M7 (CLAUDE.md §7 M7): the program filter, visual 3 by program, and visual 7 emerging keywords. */
const ALL_COLLEGES = ['CAHS', 'CASE', 'IBAM', 'CEIS', 'CMT', 'SLCN'];

beforeEach(function () {
    seedBasics();
    Cache::flush();
    $this->urdc = userWithRole(User::ROLE_URDC);
    $this->bscs = (int) Program::where('name', 'BS Computer Science')->value('id');
    $publish = fn ($r) => $r->forceFill(['status' => 'published', 'published_at' => now()])->save();
    // Earlier period (2021–2023): flood studies. Recent period (2024–2025): drone mapping appears.
    foreach ([2021, 2022, 2023] as $y) {
        $publish(makeRecord(['year' => $y, 'keywords' => 'flood risk, random forest, prediction']));
    }
    foreach ([2024, 2025, 2025] as $y) {
        $publish(makeRecord(['year' => $y, 'program_id' => $this->bscs, 'keywords' => 'drone mapping, flood risk, imagery']));
    }
});

it('filters the dashboard by program and shows that college’s programs in visual 3', function () {
    $d = Dashboard::build(ALL_COLLEGES, ['program' => $this->bscs], true);
    expect($d['kpis'][0][1])->toBe('3')->and($d['asOf'])->toBeNull()->and($d['collegeChart'])->toBe('program')
        ->and($d['charts']['college']['yAxis']['data'])->toBe(['BS Computer Science']);

    $ceis = Dashboard::build(ALL_COLLEGES, ['college' => 'CEIS'], true);
    $capstone = collect($ceis['charts']['college']['series'])->firstWhere('name', 'Capstone project');
    $byName = array_combine($ceis['charts']['college']['yAxis']['data'], array_column($capstone['data'], 'value'));
    expect($ceis['collegeChart'])->toBe('program')->and($byName['BS Computer Science'])->toBe(3)->and($byName['BS Information Technology'])->toBe(3);

    expect(Dashboard::build(ALL_COLLEGES, [], true)['collegeChart'])->toBe('college');

    $this->actingAs($this->urdc)->get('/urdc/analytics?college=CEIS&program='.$this->bscs)->assertOk()
        ->assertSee('Outputs by program and type')->assertSee('All programs');
});

it('ignores a program outside the coordinator’s cluster or the chosen college', function () {
    $coord = userWithRole(User::ROLE_COORDINATOR);
    $cluster = ResearchCluster::whereHas('academicUnits', fn ($q) => $q->where('code', 'CMT'))->firstOrFail();
    $cluster->update(['coordinator_user_id' => $coord->id]);

    // A CEIS program in a CMT/CAHS coordinator's address is dropped: their cluster has no CEIS studies.
    $this->actingAs($coord)->get('/coordinator/analytics?program='.$this->bscs)->assertOk()->assertDontSee('BS Computer Science');
    $req = fn (array $q) => Request::create('/x', 'GET', $q);
    expect(Dashboard::filters($req(['college' => 'CMT', 'program' => $this->bscs]), ALL_COLLEGES))->not->toHaveKey('program')
        ->and(Dashboard::filters($req(['college' => 'CEIS', 'program' => $this->bscs]), ALL_COLLEGES))->toHaveKey('program');
});

it('shows emerging keywords: the largest growth in share, recent vs earlier years', function () {
    $d = Dashboard::build(ALL_COLLEGES, [], true);
    expect($d['emergingPeriods'])->toBe(['recent' => '2024–2025', 'earlier' => '2021–2023'])
        ->and($d['charts']['emerging']['yAxis']['data'][0])->toBe('drone mapping')
        ->and($d['charts']['emerging']['series'][0]['data'][0])->toEqual(100.0)
        // "flood risk" is on every study in both periods: no growth, so not emerging.
        ->and($d['charts']['emerging']['yAxis']['data'])->not->toContain('flood risk');

    $this->actingAs($this->urdc)->get('/urdc/analytics')->assertOk()->assertSee('Emerging keywords')->assertSee('descriptive only');
});

it('exports the program filter, the program table and emerging keywords', function () {
    $d = Dashboard::build(ALL_COLLEGES, ['college' => 'CEIS'], true);
    $titles = array_column(DashboardExport::tables($d, true), 'title');
    expect($titles)->toContain('Outputs by program and type')->toContain('Emerging keywords')
        ->and(DashboardExport::filterText(['program' => $this->bscs]))->toBe('Program: BS Computer Science');

    $csv = $this->actingAs($this->urdc)->get('/urdc/analytics/export/csv?college=CEIS&program='.$this->bscs)->assertOk()->streamedContent();
    expect($csv)->toContain('Program: BS Computer Science')->toContain('drone mapping');
});
