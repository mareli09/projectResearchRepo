<?php

namespace App\Domain\Analytics;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\DashboardTaskRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard task mode (CLAUDE.md §7 M7, SOP 3.3): the URDC sets 5 tasks; while task mode is on,
 * each evaluator sees them one at a time on the analytics dashboard, and the time taken and whether
 * the task was completed are recorded on the server.
 */
final class DashboardTasks
{
    public const KEY = 'evaluation.tasks';

    public const COUNT = 5;

    /** @return list<string> */
    public static function defaults(): array
    {
        return [
            'How many studies did CEIS publish in 2024? Use the filters.',
            'Which research theme has the most studies?',
            'Which SDG are the most studies aligned with?',
            'Open the list of studies behind the tallest bar of “Outputs by college and type”.',
            'Export the dashboard as a PDF report.',
        ];
    }

    /** @return array{on: bool, audience: string, tasks: list<string>} */
    public static function config(): array
    {
        $v = (array) Setting::valueOf(self::KEY, []);
        $tasks = array_values(array_map('strval', (array) ($v['tasks'] ?? [])));

        return [
            'on' => (bool) ($v['on'] ?? false),
            'audience' => in_array($v['audience'] ?? '', ['evaluators', 'dashboard_users'], true) ? $v['audience'] : 'evaluators',
            'tasks' => count($tasks) === self::COUNT ? $tasks : self::defaults(),
        ];
    }

    /** @param array{on: bool, audience: string, tasks: list<string>} $new */
    public static function save(array $new, User $by): void
    {
        $old = self::config();
        Setting::updateOrCreate(['key' => self::KEY], ['value' => $new]);
        Audit::record(AuditEvent::SETTING, 'Dashboard task mode changed', null, [], $by, old: $old, new: $new);
    }

    /** Whether this user takes part: evaluator accounts, or anyone on a dashboard when the URDC chose so. */
    public static function appliesTo(User $user): bool
    {
        $c = self::config();
        if (! $c['on']) {
            return false;
        }

        return $user->user_type === 'evaluator'
            || ($c['audience'] === 'dashboard_users' && $user->hasAnyRole([User::ROLE_URDC, User::ROLE_COORDINATOR]));
    }

    /** @return Collection<int, DashboardTaskRun> keyed by task number */
    public static function runsOf(User $user): Collection
    {
        return DashboardTaskRun::where('user_id', $user->id)->get()->keyBy('task_no');
    }

    /** The task this user is on: the one started, else the first not finished. Null when all 5 are finished. */
    public static function current(User $user): ?int
    {
        $runs = self::runsOf($user);
        $started = $runs->first(fn (DashboardTaskRun $r) => $r->status === 'started');
        if ($started) {
            return (int) $started->task_no;
        }
        foreach (range(1, self::COUNT) as $n) {
            if (! $runs->has($n)) {
                return $n;
            }
        }

        return null;
    }

    public static function start(User $user, int $no): DashboardTaskRun
    {
        abort_unless($no === self::current($user), 422, 'Do the tasks in order.');
        $run = DashboardTaskRun::firstOrCreate(['user_id' => $user->id, 'task_no' => $no], ['started_at' => now(), 'status' => 'started']);
        Audit::record(AuditEvent::VIEW, "Dashboard task {$no} started", null, ['task' => $no], $user);

        return $run;
    }

    public static function finish(User $user, int $no, string $status, ?string $answer): DashboardTaskRun
    {
        $run = DashboardTaskRun::where(['user_id' => $user->id, 'task_no' => $no, 'status' => 'started'])->firstOrFail();
        $run->update(['finished_at' => now(), 'status' => $status, 'answer' => $answer !== null ? mb_substr(strip_tags($answer), 0, 500) : null]);
        $secs = $run->seconds();
        Audit::record(AuditEvent::VIEW, "Dashboard task {$no} ".($status === 'done' ? 'done' : 'skipped')." in {$secs} s", null, ['task' => $no, 'status' => $status, 'seconds' => $secs], $user);

        return $run;
    }

    /**
     * One row per person who took part, with each task's seconds and status.
     *
     * @return list<array{user: string, email: string, tasks: array<int, array{status: string, seconds: ?int, answer: ?string}>, done: int, total_seconds: int}>
     */
    public static function results(): array
    {
        return DashboardTaskRun::with('user:id,name,email')->orderBy('user_id')->orderBy('task_no')->get()->groupBy('user_id')
            ->map(function (Collection $runs) {
                $u = $runs->first()->user;
                $tasks = [];
                foreach ($runs as $r) {
                    $tasks[(int) $r->task_no] = ['status' => $r->status, 'seconds' => $r->seconds(), 'answer' => $r->answer];
                }

                return [
                    'user' => (string) ($u->name ?? '—'), 'email' => (string) ($u->email ?? ''), 'tasks' => $tasks,
                    'done' => $runs->where('status', 'done')->count(),
                    'total_seconds' => (int) $runs->sum(fn (DashboardTaskRun $r) => (int) $r->seconds()),
                ];
            })->values()->all();
    }

    /** @return array<int, array{done: int, skipped: int, avg: ?float, rate: ?float}> per task: completion and mean seconds of the done ones */
    public static function summary(): array
    {
        $runs = DashboardTaskRun::whereIn('status', ['done', 'skipped'])->get();
        $out = [];
        foreach (range(1, self::COUNT) as $n) {
            $t = $runs->where('task_no', $n);
            $done = $t->where('status', 'done');
            $out[$n] = [
                'done' => $done->count(), 'skipped' => $t->where('status', 'skipped')->count(),
                'avg' => $done->isEmpty() ? null : round($done->avg(fn (DashboardTaskRun $r) => (int) $r->seconds()), 1),
                'rate' => $t->isEmpty() ? null : round($done->count() / $t->count() * 100, 1),
            ];
        }

        return $out;
    }
}
