<?php

use App\Domain\Audit\Activity;
use App\Models\RecoveryCode as RecoveryCodeRow;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PasswordChanged;
use App\Notifications\RecoveryCode;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedBasics();
    Notification::fake();
});

/** Steps 1–3; returns the code that was sent to $destination. */
function requestCode(string $email, string $channel, string $destination): string
{
    test()->post('/forgot-password', ['email' => $email])->assertRedirect(route('password.channel'));
    test()->post('/forgot-password/channel', ['channel' => $channel])->assertRedirect(route('password.code'));

    $code = null;
    $route = $channel === 'sms' ? SmsChannel::class : 'mail';
    Notification::assertSentOnDemand(RecoveryCode::class, function (RecoveryCode $n, array $channels, AnonymousNotifiable $to) use (&$code, $route, $destination) {
        if (($to->routes[$route] ?? null) !== $destination) {
            return false;
        }
        $code = secretOf($n, 'code');

        return true;
    });

    return $code;
}

it('resets the password with a code sent to the TUA email', function () {
    $user = User::factory()->create();
    $code = requestCode($user->email, 'tua_email', $user->email);
    expect($code)->toMatch('/^\d{6}$/');

    // The code is stored only as a hash.
    expect(Hash::check($code, RecoveryCodeRow::where('user_id', $user->id)->value('code_hash')))->toBeTrue();
    expect(RecoveryCodeRow::where('code_hash', $code)->exists())->toBeFalse();

    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026'])
        ->assertRedirect(route('login'));

    expect(Hash::check('NewResearch2026', $user->fresh()->password))->toBeTrue();
    Notification::assertSentTo($user, PasswordChanged::class);
    expect(Activity::where('event', 'password_reset')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('signs out all other sessions after a reset', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $code = requestCode($user->email, 'tua_email', $user->email);
    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026']);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('answers the same whether or not the account exists', function () {
    $this->post('/forgot-password', ['email' => 'nobody@tua.edu.ph'])->assertRedirect(route('password.channel'));
    $this->get('/forgot-password/channel')->assertOk()->assertSee('TUA email');
    $this->post('/forgot-password/channel', ['channel' => 'tua_email'])
        ->assertRedirect(route('password.code'))
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'If the account exists'));

    Notification::assertNothingSent();
});

it('shows only part of each contact', function () {
    $user = User::factory()->create(['email' => 'jasmine.villanueva@tua.edu.ph']);
    $user->forceFill(['recovery_email' => 'jasmine.v@gmail.com', 'recovery_email_verified_at' => now()])->save();

    $this->post('/forgot-password', ['email' => $user->email]);
    $this->get('/forgot-password/channel')
        ->assertSee('j****************a@tua.edu.ph')
        ->assertSee('j*******v@gmail.com')
        ->assertDontSee('jasmine.v@gmail.com');
});

it('can send the code to a verified recovery email', function () {
    $user = User::factory()->create();
    $user->forceFill(['recovery_email' => 'me@gmail.com', 'recovery_email_verified_at' => now()])->save();

    $code = requestCode($user->email, 'recovery_email', 'me@gmail.com');
    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026'])
        ->assertRedirect(route('login'));
});

it('never sends a code to an unverified recovery contact', function () {
    $user = User::factory()->create();
    $user->forceFill(['recovery_email' => 'me@gmail.com', 'recovery_email_verified_at' => null])->save();

    $this->post('/forgot-password', ['email' => $user->email]);
    $this->get('/forgot-password/channel')->assertDontSee('Recovery email');
    $this->post('/forgot-password/channel', ['channel' => 'recovery_email'])->assertSessionHasErrors('channel');

    Notification::assertNothingSent();
});

it('does not offer SMS while it is switched off', function () {
    config(['tua.features.recovery_sms' => false]);
    $user = User::factory()->create();
    $user->forceFill(['recovery_mobile' => '+639171234567', 'recovery_mobile_verified_at' => now()])->save();

    $this->post('/forgot-password', ['email' => $user->email]);
    $this->get('/forgot-password/channel')->assertDontSee('SMS');
    $this->post('/forgot-password/channel', ['channel' => 'sms'])->assertSessionHasErrors('channel');
});

it('expires the code after 10 minutes', function () {
    $user = User::factory()->create();
    $code = requestCode($user->email, 'tua_email', $user->email);
    $this->travel(11)->minutes();

    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026'])
        ->assertSessionHasErrors('code');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('stops the code after 5 wrong tries', function () {
    $user = User::factory()->create();
    $code = requestCode($user->email, 'tua_email', $user->email);
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $i) {
        $this->post('/forgot-password/code', ['code' => $wrong, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026']);
        $this->travel(13)->seconds(); // stay under the recovery rate limit
    }
    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026'])
        ->assertSessionHasErrors('code');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('works only once', function () {
    $user = User::factory()->create();
    $code = requestCode($user->email, 'tua_email', $user->email);
    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'NewResearch2026', 'password_confirmation' => 'NewResearch2026']);

    $this->withSession(['recovery' => ['email' => $user->email, 'channel' => 'tua_email']])
        ->post('/forgot-password/code', ['code' => $code, 'password' => 'Another2026pass', 'password_confirmation' => 'Another2026pass'])
        ->assertSessionHasErrors('code');
    expect(Hash::check('NewResearch2026', $user->fresh()->password))->toBeTrue();
});

it('does not use up a try when only the new password is invalid', function () {
    $user = User::factory()->create();
    $code = requestCode($user->email, 'tua_email', $user->email);

    $this->post('/forgot-password/code', ['code' => $code, 'password' => 'short1', 'password_confirmation' => 'short1'])
        ->assertSessionHasErrors('password');
    expect(RecoveryCodeRow::where('user_id', $user->id)->value('attempts'))->toBe(0);
});
