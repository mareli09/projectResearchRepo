<?php

use App\Domain\Audit\Activity;
use App\Models\User;

beforeEach(fn () => seedBasics());

it('signs in an active TUA user, logs it and records the time', function () {
    $user = userWithRole(User::ROLE_STUDENT);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
    expect(Activity::where('event', 'login')->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('regenerates the session on sign-in', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

it('refuses a non-TUA email at sign-in', function () {
    User::factory()->create(['email' => 'someone@gmail.com']);

    $this->post('/login', ['email' => 'someone@gmail.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Use your TUA email address (@tua.edu.ph).']);
    $this->assertGuest();
});

it('refuses an account that is not activated yet', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(Activity::where('event', 'login_failed')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('refuses a deactivated account', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account is deactivated. Contact the URDC for help.']);
    $this->assertGuest();
});

it('logs a wrong password without storing the password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-secret-99'])->assertSessionHasErrors('email');

    $entry = Activity::where('event', 'login_failed')->latest('id')->firstOrFail();
    expect(json_encode($entry->properties))->not->toContain('wrong-secret-99');
    expect($entry->properties['details']['email'])->toBe($user->email);
});

it('pauses sign-in after 5 failed attempts per email and IP', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many');
    $this->assertGuest();
    expect(Activity::where('event', 'login_failed')->where('properties->details->reason', 'rate_limited')->exists())->toBeTrue();

    $this->travel(61)->seconds();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

it('ends the session of a user who gets deactivated', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($user)->get('/account')->assertOk();

    $user->forceFill(['deactivated_at' => now()])->save();
    $this->get('/account')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs sign-out', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    expect(Activity::where('event', 'logout')->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('uses a 30-minute idle session', function () {
    expect(config('session.lifetime'))->toBe(30);
    expect(config('session.expire_on_close'))->toBeFalse();
});
