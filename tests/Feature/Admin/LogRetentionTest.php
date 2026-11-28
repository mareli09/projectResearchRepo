<?php

use App\Domain\Admin\SystemSettings;
use App\Domain\Audit\Activity;
use App\Domain\Audit\Audit;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/* M8 activity log retention (CLAUDE.md §7 M8): set in System settings, applied by `audit:prune` only. */
beforeEach(function () {
    seedBasics();
    $this->admin = userWithRole(User::ROLE_SYSADMIN);
});

/** A log entry dated in the past. The query builder is used on purpose: the model refuses edits. */
function oldEntry(string $description, int $yearsAgo): void
{
    Audit::record('view', $description);
    DB::table('activity_log')->where('description', $description)->update(['created_at' => now()->subYears($yearsAgo)->subDay()]);
}

it('defaults to 5 years and saves a new retention with the change logged', function () {
    expect(SystemSettings::logRetentionYears())->toBe(5);

    $this->actingAs($this->admin)->post('/admin/settings/retention', ['years' => 3])->assertRedirect('/admin/settings#retention')->assertSessionHasNoErrors();
    expect(SystemSettings::logRetentionYears())->toBe(3);
    $log = Activity::where('event', 'setting')->sole();
    expect($log->properties['old'])->toBe(['years' => 5])->and($log->properties['new'])->toBe(['years' => 3]);

    $this->actingAs($this->admin)->post('/admin/settings/retention', ['years' => 0])->assertSessionHasErrorsIn('retention', 'years');
    $this->actingAs($this->admin)->post('/admin/settings/retention', ['years' => 11])->assertSessionHasErrorsIn('retention', 'years');
    $this->actingAs(userWithRole(User::ROLE_URDC))->post('/admin/settings/retention', ['years' => 1])->assertForbidden();

    $this->actingAs($this->admin)->get('/activity')->assertOk()->assertSee('They are kept for 3 years');
});

it('removes only entries past the retention period, and logs the removal', function () {
    $this->actingAs($this->admin)->post('/admin/settings/retention', ['years' => 2]);
    oldEntry('Old view A', 3);
    oldEntry('Old view B', 6);
    oldEntry('Recent view', 1);

    $this->artisan('audit:prune --dry-run')->expectsOutputToContain('2 entries are older than')->assertSuccessful();
    expect(Activity::where('description', 'like', 'Old view%')->count())->toBe(2);

    $this->artisan('audit:prune')->expectsOutputToContain('2 entries older than')->assertSuccessful();
    expect(Activity::where('description', 'like', 'Old view%')->count())->toBe(0)
        ->and(Activity::where('description', 'Recent view')->exists())->toBeTrue();
    $removal = Activity::where('event', 'setting')->where('description', 'like', 'Activity log retention:%')->sole();
    expect($removal->properties['details']['removed'])->toBe(2)->and($removal->properties['details']['retention_years'])->toBe(2);

    // Nothing left to remove: no new log entry.
    $this->artisan('audit:prune')->assertSuccessful();
    expect(Activity::where('description', 'like', 'Activity log retention:%')->count())->toBe(1);
});

it('is scheduled nightly, and single entries still cannot be deleted', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
    expect($events)->toContain('audit:prune');

    Audit::record('view', 'Keep me');
    expect(fn () => Activity::where('description', 'Keep me')->first()?->delete())->toThrow(LogicException::class);
});
