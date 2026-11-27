<?php

use App\Domain\Audit\Activity;
use App\Models\User;
use App\Notifications\ActivationLink;
use App\Notifications\AssistedResetLink;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedBasics();
    Notification::fake();
});

it('opens Users and roles for the system administrator and Account help for the URDC', function () {
    userWithRole(User::ROLE_STUDENT);
    $this->actingAs(userWithRole(User::ROLE_SYSADMIN))->get('/admin/users')->assertOk()->assertSee('Users and roles')->assertSee('Assign role');
    $this->actingAs(userWithRole(User::ROLE_URDC))->get('/admin/users')->assertOk()->assertSee('Account help')->assertDontSee('Assign role');
});

it('is closed to students, advisers and coordinators', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/admin/users')->assertForbidden();
})->with([User::ROLE_STUDENT, User::ROLE_ADVISER, User::ROLE_COORDINATOR]);

it('lets only the system administrator assign roles, and logs old and new', function () {
    $admin = userWithRole(User::ROLE_SYSADMIN);
    $target = userWithRole(User::ROLE_ADVISER);

    $this->actingAs($admin)->put("/admin/users/{$target->id}/role", ['role' => User::ROLE_COORDINATOR])->assertSessionHas('status');

    expect($target->fresh()->hasRole(User::ROLE_COORDINATOR))->toBeTrue();
    $entry = Activity::where('event', 'role')->where('subject_id', $target->id)->latest('id')->firstOrFail();
    expect($entry->properties['old'])->toBe(['role' => User::ROLE_ADVISER]);
    expect($entry->properties['new'])->toBe(['role' => User::ROLE_COORDINATOR]);
    expect($entry->causer_id)->toBe($admin->id);
});

it('does not let the URDC or anyone else assign roles', function (string $role) {
    $target = userWithRole(User::ROLE_STUDENT);

    $this->actingAs(userWithRole($role))->put("/admin/users/{$target->id}/role", ['role' => User::ROLE_SYSADMIN])->assertForbidden();
    expect($target->fresh()->hasRole(User::ROLE_SYSADMIN))->toBeFalse();
})->with([User::ROLE_URDC, User::ROLE_COORDINATOR, User::ROLE_ADVISER, User::ROLE_STUDENT]);

it('does not let an administrator change their own role', function () {
    $admin = userWithRole(User::ROLE_SYSADMIN);
    $this->actingAs($admin)->put("/admin/users/{$admin->id}/role", ['role' => User::ROLE_STUDENT])->assertForbidden();
});

it('deactivates a user, signs them out, and reactivates them', function () {
    $admin = userWithRole(User::ROLE_SYSADMIN);
    $target = userWithRole(User::ROLE_STUDENT);
    DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin)->post("/admin/users/{$target->id}/deactivate");
    expect($target->fresh()->canSignIn())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();

    $this->post("/admin/users/{$target->id}/reactivate");
    expect($target->fresh()->canSignIn())->toBeTrue();
});

it('resends an activation link', function () {
    $target = User::factory()->inactive()->create();
    $this->actingAs(userWithRole(User::ROLE_URDC))->post("/admin/users/{$target->id}/resend-activation");

    Notification::assertSentTo($target, ActivationLink::class);
});

it('sends an assisted recovery link to the new recovery email only after an ID check', function () {
    $staff = userWithRole(User::ROLE_URDC);
    $target = userWithRole(User::ROLE_STUDENT);

    $this->actingAs($staff)->post("/admin/users/{$target->id}/assisted-recovery", [
        'id_type' => 'TUA ID', 'recovery_email' => 'help@gmail.com',
    ])->assertSessionHasErrors('id_checked');

    $this->post("/admin/users/{$target->id}/assisted-recovery", [
        'id_checked' => '1', 'id_type' => 'TUA ID', 'recovery_email' => 'help@gmail.com',
    ])->assertSessionHas('status');

    $token = null;
    Notification::assertSentOnDemand(AssistedResetLink::class, function (AssistedResetLink $n, $c, AnonymousNotifiable $to) use (&$token) {
        $token = secretOf($n, 'token');

        return $to->routes['mail'] === 'help@gmail.com';
    });
    expect(Activity::where('event', 'recovery')->where('subject_id', $target->id)->exists())->toBeTrue();

    // The user sets the password from the link. Staff never see it.
    auth()->logout();
    $this->post('/reset-password', [
        'token' => $token, 'email' => $target->email, 'password' => 'Recovered2026', 'password_confirmation' => 'Recovered2026',
    ])->assertRedirect(route('login'));
    expect(Hash::check('Recovered2026', $target->fresh()->password))->toBeTrue();
});
