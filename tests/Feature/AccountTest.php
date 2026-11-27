<?php

use App\Domain\Audit\Activity;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\RecoveryCode;
use App\Notifications\RecoveryContactChanged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedBasics();
    Notification::fake();
});

function codeSentTo(string $destination): string
{
    $code = null;
    Notification::assertSentOnDemand(RecoveryCode::class, function (RecoveryCode $n, array $c, AnonymousNotifiable $to) use (&$code, $destination) {
        if (! in_array($destination, $to->routes, true)) {
            return false;
        }
        $code = secretOf($n, 'code');

        return true;
    });

    return $code;
}

it('is available to every role', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/account')->assertOk()->assertSee('Account and recovery');
})->with(User::ROLES);

it('is not available to guests', function () {
    $this->get('/account')->assertRedirect(route('login'));
});

it('adds a recovery email, verifies it with a code, and tells the TUA email', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($user)->post('/account/recovery/email', ['recovery_email' => 'Me@Gmail.com'])->assertSessionHas('status');

    $user->refresh();
    expect($user->recovery_email)->toBe('me@gmail.com');
    expect($user->recovery_email_verified_at)->toBeNull();
    Notification::assertSentTo($user, RecoveryContactChanged::class);

    $code = codeSentTo('me@gmail.com');
    $this->post('/account/recovery/email/verify', ['code' => $code])->assertSessionHas('status');
    expect($user->fresh()->recovery_email_verified_at)->not->toBeNull();
});

it('refuses a TUA address as the recovery email', function () {
    $this->actingAs(userWithRole(User::ROLE_STUDENT))
        ->post('/account/recovery/email', ['recovery_email' => 'other@tua.edu.ph'])
        ->assertSessionHasErrors('recovery_email');
});

it('refuses a wrong verification code', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($user)->post('/account/recovery/email', ['recovery_email' => 'me@gmail.com']);
    $code = codeSentTo('me@gmail.com');

    $this->post('/account/recovery/email/verify', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code_email');
    expect($user->fresh()->recovery_email_verified_at)->toBeNull();
});

it('tells the old verified recovery email when it is replaced', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $user->forceFill(['recovery_email' => 'old@gmail.com', 'recovery_email_verified_at' => now()])->save();

    $this->actingAs($user)->post('/account/recovery/email', ['recovery_email' => 'new@gmail.com']);

    Notification::assertSentOnDemand(RecoveryContactChanged::class, fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'old@gmail.com');
});

it('removes a recovery email', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $user->forceFill(['recovery_email' => 'me@gmail.com', 'recovery_email_verified_at' => now()])->save();

    $this->actingAs($user)->delete('/account/recovery/email')->assertSessionHas('status');
    expect($user->fresh()->recovery_email)->toBeNull();
    expect(Activity::where('event', 'recovery')->where('description', 'Recovery email removed')->exists())->toBeTrue();
});

it('normalizes and verifies a PH mobile when SMS recovery is on', function () {
    config(['tua.features.recovery_sms' => true]);
    $user = userWithRole(User::ROLE_STUDENT);

    $this->actingAs($user)->post('/account/recovery/mobile', ['recovery_mobile' => '0917-123-4567'])->assertSessionHas('status');
    expect($user->fresh()->recovery_mobile)->toBe('+639171234567');

    $code = codeSentTo('+639171234567');
    $this->post('/account/recovery/mobile/verify', ['code' => $code]);
    expect($user->fresh()->recovery_mobile_verified_at)->not->toBeNull();
    $this->get('/account')->assertSee('+63 917 123 4567');
});

it('hides mobile recovery while SMS is off', function () {
    config(['tua.features.recovery_sms' => false]);
    $this->actingAs(userWithRole(User::ROLE_STUDENT))
        ->post('/account/recovery/mobile', ['recovery_mobile' => '09171234567'])
        ->assertNotFound();
});

it('changes the password, keeps this session and signs out the others', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'NewResearch2026',
        'password_confirmation' => 'NewResearch2026',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('NewResearch2026', $user->fresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
    Notification::assertSentTo($user, PasswordChanged::class);
});

it('lists the user’s own recent security events', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->get('/account')->assertSee('Signed in');
});
