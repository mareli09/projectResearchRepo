<?php

use App\Domain\Site\SiteContent;
use App\Models\AccessRequest;
use App\Models\ClusterRun;
use App\Models\SiteContentVersion;
use App\Models\Submission;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\SampleRecordSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SiteContentSeeder;
use Database\Seeders\WorkflowSampleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

it('has every table of the data model (CLAUDE.md §6)', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions', 'password_reset_tokens', 'account_activations', 'recovery_codes',
    'research_clusters', 'academic_units', 'programs', 'research_records', 'record_authors', 'keywords', 'record_keyword', 'sdgs', 'record_sdg',
    'record_files', 'submissions', 'submission_members', 'submission_reviews', 'similarity_checks', 'similarity_matches', 'screening_decisions',
    'llm_suggestions', 'cluster_runs', 'clusters', 'record_cluster', 'access_requests', 'notifications', 'mail_log', 'activity_log',
    'search_logs', 'download_logs', 'kpi_snapshots', 'settings', 'site_contents', 'evaluator_accounts',
]);

it('seeds the sample workflow data the design screens show', function () {
    Storage::fake('research');
    seedBasics();
    $this->seed([SettingsSeeder::class, DemoUserSeeder::class, SiteContentSeeder::class, SampleRecordSeeder::class, WorkflowSampleSeeder::class]);

    expect(Submission::count())->toBe(8);
    $sub = Submission::where('public_id', 'SUB-0142')->with('members', 'reviews', 'similarityChecks.matches', 'student')->firstOrFail();
    expect($sub->status)->toBe('endorsed');
    expect($sub->flag)->toBe('similar');
    expect($sub->student->email)->toBe('jasmine.villanueva@tua.edu.ph');
    expect($sub->members)->toHaveCount(3);
    expect($sub->reviews->pluck('decision')->all())->toBe(['submit', 'endorse']);
    expect($sub->similarityChecks->first()->matches)->toHaveCount(5);

    // Every return carries a reason (§1 rule 5).
    expect(DB::table('submission_reviews')->where('decision', 'return')->whereNull('reason')->count())->toBe(0);

    expect(AccessRequest::where('status', 'pending')->count())->toBe(2);
    expect(ClusterRun::where('state', 'current')->first()->clusters)->toHaveCount(7);
    expect(DB::table('record_cluster')->count())->toBe(1419);
    expect(DB::table('llm_suggestions')->where('kind', 'theme_label')->where('action', 'pending')->count())->toBe(2);
    expect(DB::table('notifications')->count())->toBeGreaterThan(0);
    expect(DB::table('search_logs')->where('results_count', 0)->count())->toBeGreaterThan(0);
});

it('runs the sample seeders only once', function () {
    Storage::fake('research');
    seedBasics();
    $this->seed([SettingsSeeder::class, DemoUserSeeder::class, SampleRecordSeeder::class, WorkflowSampleSeeder::class]);
    $this->seed(WorkflowSampleSeeder::class);

    expect(Submission::count())->toBe(8);
});

it('serves the landing page from the latest published content version', function () {
    seedBasics();
    $this->seed(SiteContentSeeder::class);
    expect(SiteContentVersion::where('status', 'published')->count())->toBe(1);

    $data = SiteContent::defaults();
    $data['hero']['title'] = 'A newer headline from version 2';
    SiteContentVersion::create(['version' => 2, 'data' => $data, 'status' => 'published', 'summary' => 'Hero', 'published_at' => now()]);
    $draft = $data;
    $draft['hero']['title'] = 'An unpublished draft headline';
    SiteContentVersion::create(['version' => 3, 'data' => $draft, 'status' => 'draft']);

    $this->get('/')->assertOk()->assertSee('A newer headline from version 2')->assertDontSee('An unpublished draft headline');
});

it('escapes landing page content', function () {
    seedBasics();
    $data = SiteContent::defaults();
    $data['hero']['title'] = '<script>alert(1)</script>';
    SiteContentVersion::create(['version' => 1, 'data' => $data, 'status' => 'published', 'published_at' => now()]);

    $this->get('/')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});
