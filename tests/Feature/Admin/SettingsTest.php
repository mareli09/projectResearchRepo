<?php

use App\Domain\Accounts\RecoveryCodeService;
use App\Domain\Admin\SystemSettings;
use App\Domain\Audit\Activity;
use App\Domain\Repository\RepositorySettings;
use App\Domain\Screening\Thresholds;
use App\Models\Setting;
use App\Models\User;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\NullLlmClient;
use App\Services\LlmClient\OpenAiLlmClient;

/*
| M9 System settings (CLAUDE.md §7): saved per group, validated, logged with old and new values,
| and applied at once by the parts of the app that use them.
*/
beforeEach(function () {
    seedBasics();
    $this->admin = userWithRole(User::ROLE_SYSADMIN);
});

it('saves the similarity thresholds and logs the old and new values', function () {
    $this->actingAs($this->admin)->post('/admin/settings/thresholds', ['duplicate' => 0.85, 'review' => 0.55])
        ->assertRedirect('/admin/settings#thresholds')->assertSessionHasNoErrors();

    expect(Setting::valueOf('screening.thresholds'))->toBe(['duplicate' => 0.85, 'review' => 0.55]);
    expect(Thresholds::flag(0.84))->toBe('similar')->and(Thresholds::flag(0.85))->toBe('probable_duplicate')->and(Thresholds::flag(0.54))->toBe('original');

    $log = Activity::where('event', 'setting')->sole();
    expect($log->properties['old'])->toBe(['duplicate' => 0.8, 'review' => 0.6])
        ->and($log->properties['new'])->toBe(['duplicate' => 0.85, 'review' => 0.55])
        ->and($log->causer_id)->toBe($this->admin->id);
});

it('refuses a review threshold that is not below the duplicate threshold', function () {
    $this->actingAs($this->admin)->post('/admin/settings/thresholds', ['duplicate' => 0.7, 'review' => 0.7])
        ->assertSessionHasErrorsIn('thresholds', ['review' => 'The review threshold must be lower than the duplicate threshold.']);

    expect(Activity::where('event', 'setting')->count())->toBe(0);
});

it('does not log a save that changes nothing', function () {
    $this->actingAs($this->admin)->post('/admin/settings/files', SystemSettings::current('files'))->assertSessionHas('status', 'File settings: nothing changed.');
    expect(Activity::where('event', 'setting')->count())->toBe(0);
});

it('applies the file limits, session timeout and sign-in attempts', function () {
    $this->actingAs($this->admin)->post('/admin/settings/files', ['max_mb' => 12, 'abstract_word_limit' => 300]);
    $this->actingAs($this->admin)->post('/admin/settings/signin', ['idle_minutes' => 45, 'login_max_attempts' => 3]);

    expect(RepositorySettings::maxFileMb())->toBe(12)
        ->and(RepositorySettings::abstractWordLimit())->toBe(300);

    $this->get('/');
    expect(config('session.lifetime'))->toBe(45);

    // 3 failed sign-ins now trigger the pause.
    auth()->logout();
    $user = userWithRole(User::ROLE_STUDENT);
    foreach (range(1, 3) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-1']);
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('switches the LLM off and applies its timeout and rate', function () {
    config(['tua.llm.enabled' => true, 'tua.llm.api_key' => 'sk-test', 'tua.llm.provider' => 'openai']);
    $this->actingAs($this->admin)->post('/admin/settings/llm', ['enabled' => '1', 'timeout' => 5, 'rate_per_minute' => 30])->assertSessionHasNoErrors();
    app()->forgetInstance(LlmClient::class);
    expect(app(LlmClient::class))->toBeInstanceOf(OpenAiLlmClient::class)
        ->and(SystemSettings::llmTimeout())->toBe(5)->and(SystemSettings::llmRate())->toBe(30);

    $this->actingAs($this->admin)->post('/admin/settings/llm', ['enabled' => '0', 'timeout' => 5, 'rate_per_minute' => 30]);
    app()->forgetInstance(LlmClient::class);
    expect(app(LlmClient::class))->toBeInstanceOf(NullLlmClient::class);
});

it('stops offering a recovery channel that is switched off', function () {
    config(['tua.features.recovery_email' => true]);
    $user = userWithRole(User::ROLE_STUDENT, ['recovery_email' => 'me@example.com', 'recovery_email_verified_at' => now()]);
    expect(RecoveryCodeService::channelsFor($user))->toHaveKey('recovery_email');

    $this->actingAs($this->admin)->post('/admin/settings/recovery', ['recovery_email' => '0', 'sms' => '0']);

    expect(RecoveryCodeService::channelsFor($user->fresh()))->not->toHaveKey('recovery_email')->toHaveKey('tua_email');
});

it('lets only the System administrator change settings', function () {
    $this->actingAs(userWithRole(User::ROLE_URDC))->post('/admin/settings/thresholds', ['duplicate' => 0.9, 'review' => 0.5])->assertForbidden();
    $this->actingAs($this->admin)->post('/admin/settings/unknown', [])->assertNotFound();
    $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('Save thresholds')->assertDontSee('data-preview', false);
});
