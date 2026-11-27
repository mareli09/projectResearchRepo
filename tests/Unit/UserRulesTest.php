<?php

use App\Models\User;

it('accepts only the exact TUA domain, case-insensitively', function (string $email, bool $ok) {
    expect(User::isTuaEmail($email))->toBe($ok);
})->with([
    ['juan.delacruz@tua.edu.ph', true],
    ['Juan.DelaCruz@TUA.EDU.PH', true],
    ['juan@gmail.com', false],
    ['juan@student.tua.edu.ph', false],
    ['juan@tua.edu.ph.evil.com', false],
    ['tua.edu.ph', false],
]);

it('lets self-registration pick only student or faculty adviser', function () {
    expect(User::SELF_ASSIGNABLE_ROLES)->toBe([User::ROLE_STUDENT, User::ROLE_ADVISER]);
});

it('blocks sign-in for inactive, deactivated or expired accounts', function () {
    $u = new User;
    $u->is_active = false;
    expect($u->canSignIn())->toBeFalse();

    $u->is_active = true;
    expect($u->canSignIn())->toBeTrue();

    $u->deactivated_at = now();
    expect($u->canSignIn())->toBeFalse();

    $u->deactivated_at = null;
    $u->expires_at = now()->subDay();
    expect($u->canSignIn())->toBeFalse();
});
