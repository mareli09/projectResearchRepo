<?php

namespace App\Domain\Accounts;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\EvaluatorAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Temporary evaluator accounts for external IT experts (CLAUDE.md §1 rule 1, §17).
 * They exist only in the evaluation copy (APP_EVALUATION_COPY=true), expire automatically,
 * and every action is logged. The evaluator sets their own password from a 48-hour activation
 * link, so the System administrator never sees or sets it.
 */
final class EvaluatorAccounts
{
    /** Roles an evaluator may act as. Never the System administrator. */
    public const ROLES = [
        User::ROLE_STUDENT => 'Student',
        User::ROLE_ADVISER => 'Faculty adviser',
        User::ROLE_URDC => 'URDC administrator',
    ];

    public static function enabled(): bool
    {
        return (bool) config('tua.evaluation_copy');
    }

    public static function defaultExpiry(): Carbon
    {
        $ends = config('tua.evaluation.ends_on');
        $date = $ends ? Carbon::parse((string) $ends, config('app.timezone')) : null;

        return $date !== null && $date->isFuture() ? $date->startOfDay() : now(config('app.timezone'))->addDays(30)->startOfDay();
    }

    public static function latestExpiry(): Carbon
    {
        return now(config('app.timezone'))->addDays((int) config('tua.evaluation.max_days'))->startOfDay();
    }

    /**
     * @param  array{label: string, email: string, role: string, academic_unit_id?: int|null, program_id?: int|null, expires_on: string}  $data
     */
    public static function create(array $data, User $by, ActivationService $activation): User
    {
        self::guard();

        $user = DB::transaction(function () use ($data, $by): User {
            $user = new User([
                'name' => $data['label'],
                'email' => Str::lower($data['email']),
                // A random password nobody knows; the evaluator sets their own on activation.
                'password' => Str::password(40),
                'user_type' => 'evaluator',
                'academic_unit_id' => $data['academic_unit_id'] ?? null,
                'program_id' => $data['program_id'] ?? null,
            ]);
            $user->is_active = false;
            $user->expires_at = Carbon::parse($data['expires_on'], config('app.timezone'))->endOfDay();
            $user->save();
            $user->assignRole($data['role']);

            EvaluatorAccount::create(['user_id' => $user->id, 'label' => $data['label'], 'created_by' => $by->id, 'expires_at' => $user->expires_at]);

            return $user;
        });

        $activation->issue($user);
        Audit::record(AuditEvent::ROLE, 'Evaluator account created', $user, [
            'label' => $data['label'], 'expires_at' => $user->expires_at?->toDateString(),
        ], $by, new: ['role' => $data['role'], 'user_type' => 'evaluator']);

        return $user;
    }

    /** Ends an evaluator account now and signs it out everywhere. */
    public static function end(User $user, User $by): void
    {
        self::guard();
        abort_unless($user->user_type === 'evaluator', 404);

        $old = $user->expires_at?->toDateString();
        $user->expires_at = now();
        $user->save();
        EvaluatorAccount::where('user_id', $user->id)->update(['expires_at' => $user->expires_at]);
        DB::table('sessions')->where('user_id', $user->id)->delete();

        Audit::record(AuditEvent::ROLE, 'Evaluator account ended', $user, [], $by, old: ['expires_at' => $old], new: ['expires_at' => $user->expires_at->toDateString()]);
    }

    /**
     * @return list<array{user: User, label: string, role: string, created: string, expires: string, status: string, tone: ?string, actions: int}>
     */
    public static function all(): array
    {
        return EvaluatorAccount::with(['user.roles'])->latest('id')->get()
            ->filter(fn (EvaluatorAccount $e) => $e->user !== null)
            ->map(function (EvaluatorAccount $e) {
                $u = $e->user;
                [$status, $tone] = match (true) {
                    $u->expires_at !== null && $u->expires_at->isPast() => ['Expired', null],
                    $u->deactivated_at !== null => ['Deactivated', 'bad'],
                    ! $u->is_active => ['Waiting for activation', 'warn'],
                    default => ['Active', 'ok'],
                };

                return [
                    'user' => $u,
                    'label' => $e->label,
                    'role' => self::ROLES[$u->getRoleNames()->first()] ?? '—',
                    'created' => $e->created_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '',
                    'expires' => $u->expires_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—',
                    'status' => $status,
                    'tone' => $tone,
                    'actions' => (int) DB::table('activity_log')->where('causer_type', $u->getMorphClass())->where('causer_id', $u->id)->count(),
                ];
            })->values()->all();
    }

    private static function guard(): void
    {
        if (! self::enabled()) {
            throw new RuntimeException('Evaluator accounts exist only in the evaluation copy (APP_EVALUATION_COPY=true).');
        }
    }
}
