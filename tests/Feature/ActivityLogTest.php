<?php

use App\Domain\Audit\Activity;
use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\User;

beforeEach(fn () => seedBasics());

it('opens for the URDC and the system administrator', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/activity')->assertOk()->assertSee('Activity log');
})->with([User::ROLE_URDC, User::ROLE_SYSADMIN]);

it('is closed to the other roles', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/activity')->assertForbidden();
    $this->actingAs(userWithRole($role))->get('/activity/export')->assertForbidden();
})->with([User::ROLE_STUDENT, User::ROLE_ADVISER, User::ROLE_COORDINATOR]);

it('stores time, user, event, details, subject, IP and user agent', function () {
    $user = userWithRole(User::ROLE_STUDENT);
    $this->actingAs($user)->withHeader('User-Agent', 'PestBrowser/1.0');

    $this->get('/dashboard'); // binds the request
    $entry = Audit::record(AuditEvent::SETTING, 'Changed a setting', $user, ['key' => 'x'], old: ['v' => 1], new: ['v' => 2]);

    expect($entry->event)->toBe('setting');
    expect($entry->causer_id)->toBe($user->id);
    expect($entry->subject_id)->toBe($user->id);
    expect($entry->properties['details'])->toBe(['key' => 'x']);
    expect($entry->properties['old'])->toBe(['v' => 1]);
    expect($entry->properties['new'])->toBe(['v' => 2]);
    expect($entry->properties)->toHaveKeys(['ip', 'user_agent']);
});

it('filters by event and user', function () {
    $a = userWithRole(User::ROLE_STUDENT, ['name' => 'Alpha Person']);
    $b = userWithRole(User::ROLE_STUDENT, ['name' => 'Beta Person']);
    Audit::record(AuditEvent::LOGIN, 'Alpha signed in', $a, causer: $a);
    Audit::record(AuditEvent::SEARCH, 'Beta searched', $b, causer: $b);

    $this->actingAs(userWithRole(User::ROLE_URDC))
        ->get('/activity?event=login&user=Alpha')
        ->assertSee('Alpha signed in')
        ->assertDontSee('Beta searched');
});

it('exports CSV', function () {
    $u = userWithRole(User::ROLE_STUDENT);
    Audit::record(AuditEvent::LOGIN, 'Exported entry', $u, causer: $u);

    $response = $this->actingAs(userWithRole(User::ROLE_URDC))->get('/activity/export');
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Exported entry')->toContain('Time (Asia/Manila)');
});

it('is append-only', function () {
    $entry = Audit::record(AuditEvent::LOGIN, 'Original');

    expect(fn () => $entry->update(['description' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect(Activity::find($entry->id)->description)->toBe('Original');
});

it('has every event type from the spec', function () {
    expect(AuditEvent::all())->toHaveCount(29)->toContain('login_failed', 'metadata_edit', 'cluster_run', 'cms');
});
