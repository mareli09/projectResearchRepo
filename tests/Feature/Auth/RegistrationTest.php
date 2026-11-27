<?php

use App\Domain\Audit\Activity;
use App\Models\AccountActivation;
use App\Models\Program;
use App\Models\User;
use App\Notifications\ActivationLink;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedBasics();
    Notification::fake();
});

function registration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan.delacruz@tua.edu.ph',
        'account_type' => 'student',
        'academic_unit_id' => ceisId(),
        'program_id' => bsitId(),
        'password' => 'Research2026x',
        'password_confirmation' => 'Research2026x',
        'privacy' => '1',
    ], $overrides);
}

it('shows the create account page', function () {
    $this->get('/register')->assertOk()->assertSee('Create account')->assertSee('Privacy Notice');
});

it('creates an inactive student and emails a one-time activation link', function () {
    $this->post('/register', registration())->assertRedirect(route('register.done'));

    $user = User::where('email', 'juan.delacruz@tua.edu.ph')->firstOrFail();
    expect($user->is_active)->toBeFalse();
    expect($user->hasRole(User::ROLE_STUDENT))->toBeTrue();
    expect($user->canSignIn())->toBeFalse();

    $token = null;
    Notification::assertSentTo($user, ActivationLink::class, function (ActivationLink $n) use (&$token) {
        $token = secretOf($n, 'token');

        return true;
    });

    // Only the hash is stored (§1 rule 2).
    $row = AccountActivation::where('user_id', $user->id)->firstOrFail();
    expect($row->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
    expect($row->expires_at->diffInHours(now(), true))->toBeGreaterThan(47.9)->toBeLessThanOrEqual(48);

    expect(Activity::where('event', 'register')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('gives faculty the faculty adviser role', function () {
    $this->post('/register', registration(['account_type' => 'faculty', 'program_id' => null]))->assertRedirect();

    expect(User::where('email', 'juan.delacruz@tua.edu.ph')->first()->hasRole(User::ROLE_ADVISER))->toBeTrue();
});

it('refuses a Gmail address', function () {
    $this->post('/register', registration(['email' => 'juan@gmail.com']))
        ->assertSessionHasErrors(['email' => 'Only @tua.edu.ph addresses can register.']);
    expect(User::count())->toBe(0);
});

it('refuses look-alike TUA domains', function (string $email) {
    $this->post('/register', registration(['email' => $email]))->assertSessionHasErrors('email');
})->with(['juan@student.tua.edu.ph', 'juan@tua.edu.ph.example.com', 'juan@tuaedu.ph']);

it('accepts the TUA domain in any letter case', function () {
    $this->post('/register', registration(['email' => 'Juan.DelaCruz@TUA.EDU.PH']))->assertRedirect(route('register.done'));
    expect(User::where('email', 'juan.delacruz@tua.edu.ph')->exists())->toBeTrue();
});

it('cannot self-assign coordinator, URDC or system administrator', function (string $type) {
    $this->post('/register', registration(['account_type' => $type]))->assertSessionHasErrors('account_type');
    expect(User::count())->toBe(0);
})->with([User::ROLE_COORDINATOR, User::ROLE_URDC, User::ROLE_SYSADMIN, 'staff', 'evaluator']);

it('enforces the password rules', function (string $password) {
    $this->post('/register', registration(['password' => $password, 'password_confirmation' => $password]))
        ->assertSessionHasErrors('password');
})->with([
    'too short' => 'abc12345',
    'no number' => 'researchonly',
    'no letter' => '12345678901',
    'common' => 'Password1234',
]);

it('requires the privacy notice', function () {
    $this->post('/register', registration(['privacy' => null]))->assertSessionHasErrors('privacy');
});

it('requires a program from the chosen college', function () {
    $cahsProgram = Program::where('name', 'BS Pharmacy')->value('id');
    $this->post('/register', registration(['program_id' => $cahsProgram]))->assertSessionHasErrors('program_id');
});

it('stores an optional recovery email as unverified and refuses a TUA address there', function () {
    $this->post('/register', registration(['recovery_email' => 'juan.personal@tua.edu.ph']))->assertSessionHasErrors('recovery_email');

    $this->post('/register', registration(['recovery_email' => 'Juan.Personal@Gmail.com']))->assertRedirect();
    $user = User::where('email', 'juan.delacruz@tua.edu.ph')->first();
    expect($user->recovery_email)->toBe('juan.personal@gmail.com');
    expect($user->recovery_email_verified_at)->toBeNull();
});

it('refuses a recovery mobile while SMS recovery is off', function () {
    config(['tua.features.recovery_sms' => false]);
    $this->post('/register', registration(['recovery_mobile' => '09171234567']))->assertSessionHasErrors('recovery_mobile');
});

it('refuses a duplicate email', function () {
    User::factory()->create(['email' => 'juan.delacruz@tua.edu.ph']);
    $this->post('/register', registration())->assertSessionHasErrors('email');
});
