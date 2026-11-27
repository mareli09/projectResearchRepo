<?php

use App\Domain\Accounts\ActivationService;
use App\Models\User;
use App\Notifications\ActivationLink;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedBasics();
    Notification::fake();
});

/** Issues a link and returns the raw token from the email. */
function issueLink(User $user): string
{
    app(ActivationService::class)->issue($user);
    $token = null;
    Notification::assertSentTo($user, ActivationLink::class, function (ActivationLink $n) use (&$token) {
        $token = secretOf($n, 'token');

        return true;
    });

    return $token;
}

it('activates the account from the link, after a button press', function () {
    $user = User::factory()->inactive()->create();
    $token = issueLink($user);

    // Opening the link does not activate yet (mail scanners prefetch links).
    $this->get("/activate/{$token}")->assertOk()->assertSee('Activate my account');
    expect($user->fresh()->is_active)->toBeFalse();

    $this->post("/activate/{$token}")->assertRedirect(route('login'));
    expect($user->fresh()->is_active)->toBeTrue();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('cannot reuse a used link', function () {
    $user = User::factory()->inactive()->create();
    $token = issueLink($user);
    $this->post("/activate/{$token}");

    $user->fresh()->forceFill(['is_active' => false])->save();
    $this->post("/activate/{$token}")->assertRedirect("/activate/{$token}");
    expect($user->fresh()->is_active)->toBeFalse();
    $this->get("/activate/{$token}")->assertSee('This link was already used');
});

it('shows "Send a new link" for an expired link', function () {
    $user = User::factory()->inactive()->create();
    $token = issueLink($user);
    $this->travel(49)->hours();

    $this->get("/activate/{$token}")->assertOk()->assertSee('expired')->assertSee('Send a new link');
    $this->post("/activate/{$token}");
    expect($user->fresh()->is_active)->toBeFalse();
});

it('makes the old link invalid when a new one is sent', function () {
    $user = User::factory()->inactive()->create();
    $old = issueLink($user);

    Notification::fake();
    $this->post('/activation/resend', ['email' => $user->email])->assertRedirect(route('login'));
    $new = null;
    Notification::assertSentTo($user, ActivationLink::class, function (ActivationLink $n) use (&$new) {
        $new = secretOf($n, 'token');

        return true;
    });

    $this->get("/activate/{$old}")->assertSee('not valid');
    $this->post("/activate/{$new}");
    expect($user->fresh()->is_active)->toBeTrue();
});

it('answers the same whether or not the account exists', function () {
    $a = $this->post('/activation/resend', ['email' => 'nobody@tua.edu.ph']);
    $user = User::factory()->inactive()->create();
    $b = $this->post('/activation/resend', ['email' => $user->email]);

    expect($a->getSession()->get('status'))->toBe($b->getSession()->get('status'));
});

it('does not send a link to an active or deactivated account', function () {
    $active = User::factory()->create();
    $deactivated = User::factory()->inactive()->create(['deactivated_at' => now()]);

    $this->post('/activation/resend', ['email' => $active->email]);
    $this->post('/activation/resend', ['email' => $deactivated->email]);

    Notification::assertNothingSent();
});
