<?php

use App\Domain\Audit\Activity;
use App\Domain\Llm\Suggestions;
use App\Models\LlmSuggestion;
use App\Models\Setting;
use App\Models\User;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\NullLlmClient;
use App\Services\LlmClient\OpenAiLlmClient;
use Illuminate\Support\Facades\Http;

/*
| LLM integration (CLAUDE.md §10): suggestions only, validated against the schema, with the
| top TF-IDF terms as the fallback, and every suggestion and action logged.
*/
beforeEach(function () {
    seedBasics();
    $this->student = userWithRole(User::ROLE_STUDENT, ['academic_unit_id' => ceisId(), 'program_id' => bsitId()]);
    app()->instance(LlmClient::class, new OpenAiLlmClient('sk-test', 'gpt-4o-mini'));
});

function llmReply(array|string $content, int $status = 200): array
{
    return ['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => is_string($content) ? $content : json_encode($content)]]]], $status)];
}

it('returns validated metadata suggestions from the LLM', function () {
    Http::fake(llmReply(['keywords' => ['TF-IDF', 'cosine similarity', 'tf-idf', 'a keyword that has far too many words', 'research repository'],
        'sdgs' => [4, 99, '9'], 'research_type' => 'Capstone project']));

    $r = app(Suggestions::class)->metadata('A research repository with TF-IDF', 'Screens titles with cosine similarity.');

    expect($r['source'])->toBe('llm')
        ->and($r['keywords'])->toBe(['tf-idf', 'cosine similarity', 'research repository'])
        ->and($r['sdgs'])->toBe([4, 9])
        ->and($r['research_type'])->toBe('capstone_project')
        ->and($r['provider'])->toBe('openai:gpt-4o-mini');

    // Only the title and abstract are sent; the key goes in the header, never in the body.
    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer sk-test') && $req['response_format']['type'] === 'json_object'
        && str_contains($req['messages'][1]['content'], 'Title: A research repository') && ! str_contains(json_encode($req->data()), 'sk-test'));
    expect(Activity::where('event', 'service')->latest('id')->first()->properties['details']['fields_sent'])->toBe(['title', 'abstract']);
});

it('falls back to the top TF-IDF terms when the LLM fails', function (string $content, int $status) {
    Http::fake(llmReply($content, $status) + ['127.0.0.1:8001/top-terms' => Http::response(['terms' => ['repository', 'screening', 'cosine']])]);

    $r = app(Suggestions::class)->metadata('Title', 'Abstract text');

    expect($r['source'])->toBe('fallback')->and($r['keywords'])->toBe(['repository', 'screening', 'cosine'])
        ->and($r['sdgs'])->toBe([])->and($r['research_type'])->toBeNull()
        ->and($r['message'])->toBe('Suggestions unavailable, showing top terms');
})->with([
    'server error' => ['', 500],
    'not JSON' => ['Sure! Here are keywords: a, b', 200],
    'no usable keywords' => ['{"keywords": ["!!!"], "sdgs": [1]}', 200],
]);

it('uses local terms when both the LLM and the analytics service are down', function () {
    Http::fake(['api.openai.com/*' => Http::response('', 500), '127.0.0.1:8001/*' => Http::response('', 500)]);

    $r = app(Suggestions::class)->metadata('Telemedicine readiness of rural clinics', 'Telemedicine readiness was measured in rural clinics.');

    expect($r['source'])->toBe('fallback')->and($r['keywords'])->toContain('telemedicine');
});

it('uses the null client when the LLM is switched off in Settings', function () {
    config(['tua.llm.enabled' => true, 'tua.llm.api_key' => 'sk-test', 'tua.llm.provider' => 'openai']);
    Setting::updateOrCreate(['key' => 'llm.enabled'], ['value' => false]);
    app()->forgetInstance(LlmClient::class);

    expect(app(LlmClient::class))->toBeInstanceOf(NullLlmClient::class);
});

it('serves suggestions to the submit form, rate limited and for students only', function () {
    Http::fake(llmReply(['keywords' => ['telemedicine', 'rural health', 'readiness'], 'sdgs' => [3], 'research_type' => 'Undergraduate thesis']));

    $this->actingAs($this->student)->postJson('/student/suggest', ['title' => 'Telemedicine readiness', 'abstract' => 'Rural clinics.'])
        ->assertOk()->assertJson(['source' => 'llm', 'keywords' => ['telemedicine', 'rural health', 'readiness'], 'sdgs' => [3], 'research_type' => 'undergraduate_thesis']);
    $this->actingAs($this->student)->postJson('/student/suggest', ['title' => ''])->assertUnprocessable();
    $this->actingAs(userWithRole(User::ROLE_ADVISER))->postJson('/student/suggest', ['title' => 'x', 'abstract' => 'y'])->assertForbidden();
});

it('logs what the student did with each suggestion', function () {
    $sub = makeRecord(); // any subject model; on submit it is the submission

    $n = Suggestions::record($sub, $this->student, [
        ['kind' => 'keyword', 'suggested' => 'telemedicine', 'action' => 'accepted'],
        ['kind' => 'keyword', 'suggested' => 'rural health', 'final' => 'rural clinics', 'action' => 'edited'],
        ['kind' => 'sdg', 'suggested' => '3', 'action' => 'rejected'],
        ['kind' => 'bogus', 'suggested' => 'x', 'action' => 'accepted'],
    ], ['source' => 'llm', 'provider' => 'openai:gpt-4o-mini', 'latency_ms' => 640]);

    expect($n)->toBe(3);
    expect(LlmSuggestion::pluck('action', 'suggested')->all())->toBe(['telemedicine' => 'accepted', 'rural health' => 'edited', '3' => 'rejected']);
    expect(LlmSuggestion::where('suggested', 'rural health')->value('final'))->toBe('rural clinics');
    expect(Activity::where('event', 'llm')->count())->toBe(3);
});
