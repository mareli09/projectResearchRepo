<?php

use App\Domain\Audit\Activity;
use App\Models\AccountActivation;
use App\Models\EvaluatorAccount;
use App\Models\User;
use App\Notifications\ActivationLink;
use Illuminate\Support\Facades\Notification;

/* Evaluator accounts (CLAUDE.md §1 rule 1, §17): evaluation copy only, expire, every action logged. */
beforeEach(function () {
    seedBasics();
    $this->admin = userWithRole(User::ROLE_SYSADMIN);
});

function evaluatorForm(array $overrides = []): array
{
    return array_merge([
        'label' => 'IT expert 01', 'email' => 'expert01@example.org', 'role' => User::ROLE_URDC,
        'expires_on' => now()->addDays(20)->toDateString(),
    ], $overrides);
}

/** Creates an evaluator through the page and returns the raw activation token from the email. */
function createEvaluator(array $overrides = []): array
{
    $token = null;
    Notification::fake();
    test()->actingAs(test()->admin)->post('/admin/evaluators', evaluatorForm($overrides))->assertSessionHasNoErrors();
    $user = User::where('email', $overrides['email'] ?? 'expert01@example.org')->firstOrFail();
    Notification::assertSentTo($user, ActivationLink::class, function (ActivationLink $n) use (&$token, $user) {
        preg_match('#/activate/([^/?]+)#', $n->toMail($user)->actionUrl, $m);
        $token = $m[1];

        return true;
    });

    return [$user, $token];
}

it('exists only in the evaluation copy', function () {
    config(['tua.evaluation_copy' => false]);

    $this->actingAs($this->admin)->get('/admin/evaluators')->assertOk()->assertSee('This is not the evaluation copy')->assertDontSee('data-preview', false);
    $this->actingAs($this->admin)->post('/admin/evaluators', evaluatorForm())->assertForbidden();
    expect(User::where('user_type', 'evaluator')->exists())->toBeFalse();
});

it('creates an inactive, expiring evaluator and emails a link; the admin never sets the password', function () {
    config(['tua.evaluation_copy' => true]);
    [$user] = createEvaluator();

    expect($user->user_type)->toBe('evaluator')->and($user->is_active)->toBeFalse()
        ->and($user->hasRole(User::ROLE_URDC))->toBeTrue()
        ->and($user->expires_at->toDateString())->toBe(now()->addDays(20)->toDateString())
        ->and(EvaluatorAccount::where('user_id', $user->id)->value('label'))->toBe('IT expert 01')
        ->and(AccountActivation::where('user_id', $user->id)->exists())->toBeTrue();
    expect(Activity::where('event', 'role')->where('description', 'Evaluator account created')->exists())->toBeTrue();

    $this->actingAs($this->admin)->get('/admin/evaluators')->assertSee('IT expert 01')->assertSee('Waiting for activation');
});

it('lets the evaluator set a password on activation and sign in with a non-TUA email', function () {
    config(['tua.evaluation_copy' => true]);
    [$user, $token] = createEvaluator();
    auth()->logout(); // the rest happens as the evaluator, a guest until activated

    $this->get("/activate/{$token}")->assertOk()->assertSee('Choose your password');
    $this->post("/activate/{$token}", ['password' => 'short'])->assertSessionHasErrors('password');
    $this->post("/activate/{$token}", ['password' => 'Evaluate2026x', 'password_confirmation' => 'Evaluate2026x'])->assertRedirect(route('login'));

    expect($user->fresh()->is_active)->toBeTrue();
    $this->post('/login', ['email' => 'expert01@example.org', 'password' => 'Evaluate2026x'])->assertRedirect();
    $this->assertAuthenticatedAs($user->fresh());
});

it('logs every page an evaluator opens', function () {
    config(['tua.evaluation_copy' => true]);
    $ev = userWithRole(User::ROLE_URDC, ['user_type' => 'evaluator', 'email' => 'ev@example.org', 'expires_at' => now()->addWeek()]);

    $this->actingAs($ev)->get('/search')->assertOk();
    expect(Activity::where('event', 'view')->where('causer_id', $ev->id)->where('description', 'Evaluator opened /search')->exists())->toBeTrue();
});

it('signs out an evaluator who expired, was ended, or is outside the evaluation copy', function () {
    config(['tua.evaluation_copy' => true]);
    $ev = userWithRole(User::ROLE_STUDENT, ['user_type' => 'evaluator', 'email' => 'ev@example.org', 'expires_at' => now()->subMinute()]);
    $this->actingAs($ev)->get('/search')->assertRedirect(route('login'));

    $ev2 = userWithRole(User::ROLE_STUDENT, ['user_type' => 'evaluator', 'email' => 'ev2@example.org', 'expires_at' => now()->addWeek()]);
    config(['tua.evaluation_copy' => false]);
    $this->actingAs($ev2)->get('/search')->assertRedirect(route('login'));
});

it('ends an evaluator account now', function () {
    config(['tua.evaluation_copy' => true]);
    [$user] = createEvaluator();

    $this->actingAs($this->admin)->post("/admin/evaluators/{$user->id}/end")->assertSessionHas('status');
    expect($user->fresh()->expires_at->isPast())->toBeTrue()->and($user->fresh()->canSignIn())->toBeFalse();
    expect(Activity::where('event', 'role')->where('description', 'Evaluator account ended')->exists())->toBeTrue();

    $regular = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($this->admin)->post("/admin/evaluators/{$regular->id}/end")->assertNotFound();
});

it('checks the form: no System administrator role, a college for students, at most the allowed period', function () {
    config(['tua.evaluation_copy' => true]);

    $this->actingAs($this->admin)->post('/admin/evaluators', evaluatorForm(['role' => User::ROLE_SYSADMIN]))->assertSessionHasErrors('role');
    $this->actingAs($this->admin)->post('/admin/evaluators', evaluatorForm(['role' => User::ROLE_STUDENT]))->assertSessionHasErrors(['academic_unit_id', 'program_id']);
    $this->actingAs($this->admin)->post('/admin/evaluators', evaluatorForm(['expires_on' => now()->addDays(200)->toDateString()]))->assertSessionHasErrors('expires_on');
    $this->actingAs($this->admin)->post('/admin/evaluators', evaluatorForm(['expires_on' => now()->subDay()->toDateString()]))->assertSessionHasErrors('expires_on');
    expect(User::where('user_type', 'evaluator')->exists())->toBeFalse();
});

it('is for the System administrator only', function () {
    config(['tua.evaluation_copy' => true]);
    $this->actingAs(userWithRole(User::ROLE_URDC))->get('/admin/evaluators')->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_URDC))->post('/admin/evaluators', evaluatorForm())->assertForbidden();
});
