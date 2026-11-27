<?php

use App\Console\Commands\ScheduledClustering;
use App\Domain\Audit\Activity;
use App\Jobs\RunClustering;
use App\Models\ClusterRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    seedBasics();
});

/* [Not in manuscript] Theme color saved to the account (CLAUDE.md §9). */
it('saves the theme to the account and logs it', function () {
    $user = userWithRole(User::ROLE_STUDENT);

    $this->actingAs($user)->postJson('/account/theme', ['color' => '#7A1F2B', 'mode' => 'dark'])->assertOk()->assertJson(['color' => '#7a1f2b', 'mode' => 'dark']);

    expect($user->fresh()->theme_color)->toBe('#7a1f2b')->and($user->fresh()->theme_mode)->toBe('dark');
    expect(Activity::where('event', 'theme')->count())->toBe(1);

    // The saved theme is applied on the next page, on any device.
    $this->actingAs($user->fresh())->get('/student')->assertSee('"color":"#7a1f2b","mode":"dark"', false);

    // Reset.
    $this->actingAs($user)->postJson('/account/theme', ['color' => null, 'mode' => 'system'])->assertOk();
    expect($user->fresh()->theme_color)->toBeNull()->and($user->fresh()->theme_mode)->toBe('system');
});

it('validates the theme and needs a signed-in user', function () {
    $this->postJson('/account/theme', ['color' => '#123456'])->assertUnauthorized();
    $this->actingAs(userWithRole(User::ROLE_STUDENT))->postJson('/account/theme', ['color' => 'red'])->assertUnprocessable();
    $this->actingAs(userWithRole(User::ROLE_STUDENT))->postJson('/account/theme', ['mode' => 'neon'])->assertUnprocessable();
});

/* Clustering schedule (CLAUDE.md §7 M6). */
function publishRecords(int $n): void
{
    foreach (range(1, $n) as $i) {
        makeRecord(['title' => "Scheduled Study {$i} ".uniqid()])->forceFill(['status' => 'published', 'published_at' => now()])->save();
    }
}

it('queues a run when none exists yet', function () {
    Queue::fake();
    publishRecords(3);

    $this->artisan('clustering:scheduled')->assertSuccessful();

    expect(ClusterRun::sole()->trigger_label)->toBe('Scheduler');
    Queue::assertPushed(RunClustering::class);
});

it('follows the semester, month and new-records rules', function () {
    publishRecords(5);
    $run = ClusterRun::create(['public_id' => 'RUN-01', 'k' => 3, 'n_records' => 5, 'params' => [], 'state' => 'current', 'finished_at' => now()->subDays(40)]);

    Setting::updateOrCreate(['key' => 'clustering.schedule'], ['value' => ['every' => 'semester', 'after_new_records' => 3]]);
    expect(ScheduledClustering::dueReason())->toBeNull();

    Setting::updateOrCreate(['key' => 'clustering.schedule'], ['value' => ['every' => 'month', 'after_new_records' => 3]]);
    expect(ScheduledClustering::dueReason())->toBe('a new month');

    Setting::updateOrCreate(['key' => 'clustering.schedule'], ['value' => ['every' => 'manual', 'after_new_records' => 3]]);
    expect(ScheduledClustering::dueReason())->toBeNull();
    publishRecords(3);
    expect(ScheduledClustering::dueReason())->toBe('3 new published records');

    // Never while a run is in progress.
    ClusterRun::create(['public_id' => 'RUN-02', 'k' => 0, 'n_records' => 8, 'params' => [], 'state' => 'queued']);
    expect(ScheduledClustering::dueReason())->toBeNull();
});
