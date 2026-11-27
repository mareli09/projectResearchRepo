<?php

use App\Domain\Audit\Activity;
use App\Models\User;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\OpenAiLlmClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/* M9 System health and Services and security (CLAUDE.md §7): live figures, tests, logged calls. */
beforeEach(function () {
    seedBasics();
    $this->admin = userWithRole(User::ROLE_SYSADMIN);
});

it('shows live health figures, including a down analytics service', function () {
    Http::fake(['127.0.0.1:8001/*' => Http::response(['error' => 'down'], 503)]);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\ScreenSubmission']), 'exception' => "RuntimeException: boom\n#0", 'failed_at' => now()]);

    $this->actingAs($this->admin)->get('/admin/health')->assertOk()
        ->assertSee('Not reachable')->assertSee('ScreenSubmission')->assertSee('Failed: RuntimeException: boom')->assertSee('Needs attention')
        ->assertDontSee('data-preview', false)->assertDontSee('(sample)');
});

it('retries failed jobs and logs it', function () {
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\RefitIndex', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]), 'exception' => 'x', 'failed_at' => now()]);

    $this->actingAs($this->admin)->post('/admin/jobs/retry')->assertSessionHas('status', '1 failed jobs were put back on the queue.');
    expect(DB::table('failed_jobs')->count())->toBe(0)->and(DB::table('jobs')->count())->toBe(1);
    expect(Activity::where('event', 'service')->count())->toBe(1);
});

it('tests the analytics service, the LLM and mail, and lists the calls', function () {
    Http::fake([
        '127.0.0.1:8001/health' => Http::response(['ok' => true, 'vectorizer_version' => 'v1', 'n_docs' => 1420, 'mem_mb' => 210.5]),
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"label": "Library catalog search"}']]]]),
    ]);
    app()->instance(LlmClient::class, new OpenAiLlmClient('sk-test', 'gpt-4o-mini'));

    $this->actingAs($this->admin)->post('/admin/services/analytics/test')->assertSessionHas('status', 'Analytics service is running: 1,420 records indexed · 211 MB · v1.');
    $this->actingAs($this->admin)->post('/admin/services/llm/test')->assertSessionHas('status', 'The LLM replied with a valid label: "Library catalog search".');
    $this->actingAs($this->admin)->post('/admin/services/mail/test')->assertSessionHas('status', "A test email was sent to {$this->admin->email}.");

    $this->actingAs($this->admin)->get('/admin/services')->assertOk()
        ->assertSee('Analytics service: connection test')->assertSee('Mail: test email')->assertDontSee('(sample)');
});

it('is for the System administrator only', function () {
    $this->actingAs(userWithRole(User::ROLE_URDC))->get('/admin/health')->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_URDC))->post('/admin/services/mail/test')->assertForbidden();
});
