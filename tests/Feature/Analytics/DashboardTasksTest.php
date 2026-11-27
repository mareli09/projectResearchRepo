<?php

use App\Domain\Analytics\DashboardTasks;
use App\Domain\Audit\Activity;
use App\Models\DashboardTaskRun;
use App\Models\User;
use Illuminate\Support\Carbon;

/* Dashboard task mode (CLAUDE.md §7 M7, SOP 3.3): five set tasks, time and completion per evaluator. */
beforeEach(function () {
    seedBasics();
    $this->urdc = userWithRole(User::ROLE_URDC);
    config(['tua.evaluation_copy' => true]);
    $this->evaluator = userWithRole(User::ROLE_URDC, ['user_type' => 'evaluator', 'email' => 'expert@example.org', 'expires_at' => now()->addWeek()]);
});

function turnTasksOn(string $audience = 'evaluators'): void
{
    test()->actingAs(test()->urdc)->post('/urdc/tasks', [
        'on' => '1', 'audience' => $audience, 'tasks' => ['Task A', 'Task B', 'Task C', 'Task D', 'Task E'],
    ])->assertSessionHasNoErrors();
}

it('is off until the URDC turns it on, and the change is logged', function () {
    $this->actingAs($this->evaluator)->get('/search')->assertOk()->assertDontSee('Evaluation task');

    turnTasksOn();
    expect(DashboardTasks::config()['tasks'][1])->toBe('Task B');
    expect(Activity::where('event', 'setting')->where('description', 'Dashboard task mode changed')->exists())->toBeTrue();
    $this->actingAs($this->evaluator)->get('/urdc/analytics')->assertOk()->assertSee('Evaluation task 1 of 5')->assertSee('Task A');
});

it('times each task on the server from Start to Done, in order', function () {
    turnTasksOn();
    Carbon::setTestNow('2026-12-01 09:00:00');
    $this->actingAs($this->evaluator)->post('/tasks/2/start')->assertStatus(422); // tasks go in order
    $this->actingAs($this->evaluator)->post('/tasks/1/start')->assertRedirect();
    Carbon::setTestNow('2026-12-01 09:00:42');
    $this->actingAs($this->evaluator)->post('/tasks/1/finish', ['status' => 'done', 'answer' => '18 studies'])->assertSessionHas('status');

    $run = DashboardTaskRun::firstOrFail();
    expect($run->seconds())->toBe(42)->and($run->status)->toBe('done')->and($run->answer)->toBe('18 studies');
    $this->actingAs($this->evaluator)->get('/urdc/analytics')->assertSee('Evaluation task 2 of 5');

    $this->actingAs($this->evaluator)->post('/tasks/2/start');
    Carbon::setTestNow('2026-12-01 09:02:00');
    $this->actingAs($this->evaluator)->post('/tasks/2/finish', ['status' => 'skipped']);
    Carbon::setTestNow();

    $s = DashboardTasks::summary();
    expect($s[1])->toMatchArray(['done' => 1, 'rate' => 100.0, 'avg' => 42.0])->and($s[2])->toMatchArray(['skipped' => 1, 'rate' => 0.0]);
    $this->actingAs($this->urdc)->get('/urdc/tasks')->assertOk()->assertSee('42 s')->assertSee('not done');
    expect($this->actingAs($this->urdc)->get('/urdc/tasks/export')->streamedContent())->toContain('Task A')->toContain('18 studies');
    expect(Activity::where('event', 'view')->where('description', 'Dashboard task 1 done in 42 s')->exists())->toBeTrue();
});

it('includes coordinators and URDC staff only when the URDC chooses so', function () {
    turnTasksOn();
    expect(DashboardTasks::appliesTo($this->urdc))->toBeFalse();
    $this->actingAs($this->urdc)->post('/tasks/1/start')->assertForbidden();

    turnTasksOn('dashboard_users');
    expect(DashboardTasks::appliesTo($this->urdc))->toBeTrue()
        ->and(DashboardTasks::appliesTo(userWithRole(User::ROLE_STUDENT)))->toBeFalse();
});

it('needs five tasks and is managed by the URDC only', function () {
    $this->actingAs($this->urdc)->post('/urdc/tasks', ['on' => '1', 'audience' => 'evaluators', 'tasks' => ['Only one']])->assertSessionHasErrors('tasks');
    $this->actingAs(userWithRole(User::ROLE_COORDINATOR))->get('/urdc/tasks')->assertForbidden();
});
