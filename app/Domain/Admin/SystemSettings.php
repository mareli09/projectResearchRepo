<?php

namespace App\Domain\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "System settings" (CLAUDE.md §7 M9). Each form saves one group; every change is written to the
 * activity log as event `setting` with the old and new values. The rest of the app reads the
 * values through the helpers below, so a saved change applies at once.
 */
final class SystemSettings
{
    /** @return array<string, array<string, mixed>> group => [setting key => rules] */
    public static function rules(): array
    {
        return [
            'thresholds' => [
                'duplicate' => ['required', 'numeric', 'between:0.5,0.99'],
                'review' => ['required', 'numeric', 'between:0.2,0.95', 'lt:duplicate'],
            ],
            'schedule' => [
                'every' => ['required', 'in:semester,month,manual'],
                'after_new_records' => ['required', 'integer', 'between:0,5000'],
            ],
            'llm' => [
                'enabled' => ['boolean'],
                'timeout' => ['required', 'integer', 'between:2,30'],
                'rate_per_minute' => ['required', 'integer', 'between:1,600'],
            ],
            'files' => [
                'max_mb' => ['required', 'integer', 'between:1,100'],
                'abstract_word_limit' => ['required', 'integer', 'between:100,1000'],
            ],
            'signin' => [
                'idle_minutes' => ['required', 'integer', 'between:5,480'],
                'login_max_attempts' => ['required', 'integer', 'between:3,20'],
            ],
            'recovery' => [
                'recovery_email' => ['boolean'],
                'sms' => ['boolean'],
            ],
            'retention' => [
                'years' => ['required', 'integer', 'between:1,10'],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['review.lt' => 'The review threshold must be lower than the duplicate threshold.'];
    }

    public const TITLES = [
        'thresholds' => 'Similarity thresholds', 'schedule' => 'Clustering schedule', 'llm' => 'LLM suggestions',
        'files' => 'File settings', 'signin' => 'Sign-in settings', 'recovery' => 'Recovery channels',
        'retention' => 'Activity log retention',
    ];

    /**
     * The stored value of each group, as the form shows it.
     *
     * @return array<string, mixed>
     */
    public static function current(string $group): array
    {
        return match ($group) {
            'thresholds' => (array) Setting::valueOf('screening.thresholds', ['duplicate' => config('tua.screening.duplicate_threshold'), 'review' => config('tua.screening.review_threshold')]),
            'schedule' => (array) Setting::valueOf('clustering.schedule', ['every' => config('tua.clustering.schedule'), 'after_new_records' => config('tua.clustering.refit_after_records')]),
            'llm' => ['enabled' => (bool) Setting::valueOf('llm.enabled', true), 'timeout' => self::llmTimeout(), 'rate_per_minute' => self::llmRate()],
            'files' => ['max_mb' => (int) Setting::valueOf('files.max_mb', config('tua.repository.max_file_mb')),
                'abstract_word_limit' => (int) Setting::valueOf('repository.abstract_word_limit', config('tua.repository.abstract_word_limit'))],
            'signin' => ['idle_minutes' => self::idleMinutes(), 'login_max_attempts' => self::loginMaxAttempts()],
            'recovery' => ['tua_email' => true] + array_intersect_key((array) Setting::valueOf('recovery.channels', []), ['recovery_email' => 1, 'sms' => 1])
                + ['recovery_email' => (bool) config('tua.features.recovery_email'), 'sms' => (bool) config('tua.features.recovery_sms')],
            'retention' => ['years' => self::logRetentionYears()],
            default => [],
        };
    }

    /**
     * Saves a validated group and logs what changed. Returns the changed fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function save(string $group, array $data, User $by): array
    {
        $old = self::current($group);
        $new = match ($group) {
            'thresholds' => ['duplicate' => round((float) $data['duplicate'], 2), 'review' => round((float) $data['review'], 2)],
            'schedule' => ['every' => (string) $data['every'], 'after_new_records' => (int) $data['after_new_records']],
            'llm' => ['enabled' => (bool) ($data['enabled'] ?? false), 'timeout' => (int) $data['timeout'], 'rate_per_minute' => (int) $data['rate_per_minute']],
            'files' => ['max_mb' => (int) $data['max_mb'], 'abstract_word_limit' => (int) $data['abstract_word_limit']],
            'signin' => ['idle_minutes' => (int) $data['idle_minutes'], 'login_max_attempts' => (int) $data['login_max_attempts']],
            'recovery' => ['tua_email' => true, 'recovery_email' => (bool) ($data['recovery_email'] ?? false), 'sms' => (bool) ($data['sms'] ?? false)],
            'retention' => ['years' => (int) $data['years']],
            default => throw new \InvalidArgumentException("Unknown settings group {$group}"),
        };

        $changes = [];
        foreach ($new as $k => $v) {
            if (($old[$k] ?? null) != $v) {
                $changes[$k] = ['old' => $old[$k] ?? null, 'new' => $v];
            }
        }
        if ($changes === []) {
            return [];
        }

        DB::transaction(function () use ($group, $new): void {
            $rows = match ($group) {
                'thresholds' => ['screening.thresholds' => $new],
                'schedule' => ['clustering.schedule' => $new],
                'llm' => ['llm.enabled' => $new['enabled'], 'llm.timeout' => $new['timeout'], 'llm.rate_per_minute' => $new['rate_per_minute']],
                'files' => ['files.max_mb' => $new['max_mb'], 'repository.abstract_word_limit' => $new['abstract_word_limit']],
                'signin' => ['session.idle_minutes' => $new['idle_minutes'], 'auth.login_max_attempts' => $new['login_max_attempts']],
                'recovery' => ['recovery.channels' => $new],
                'retention' => ['audit.retention_years' => $new['years']],
            };
            foreach ($rows as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        Audit::record(AuditEvent::SETTING, self::TITLES[$group].' changed: '.implode(', ', array_keys($changes)), null, ['group' => $group], $by,
            old: array_map(fn ($c) => $c['old'], $changes), new: array_map(fn ($c) => $c['new'], $changes));

        return $changes;
    }

    public static function idleMinutes(): int
    {
        return (int) Setting::valueOf('session.idle_minutes', config('session.lifetime'));
    }

    public static function loginMaxAttempts(): int
    {
        return (int) Setting::valueOf('auth.login_max_attempts', config('tua.auth.login_max_attempts'));
    }

    public static function llmTimeout(): int
    {
        return (int) Setting::valueOf('llm.timeout', config('tua.llm.timeout'));
    }

    public static function llmRate(): int
    {
        return (int) Setting::valueOf('llm.rate_per_minute', config('tua.llm.rate_per_minute'));
    }

    /**
     * How many years activity-log entries are kept (CLAUDE.md §7 M8). The default comes from
     * ACTIVITY_LOG_RETENTION_DAYS (1,825 days = 5 years). `audit:prune` removes older entries nightly.
     */
    public static function logRetentionYears(): int
    {
        return (int) Setting::valueOf('audit.retention_years', max(1, intdiv((int) config('activitylog.delete_records_older_than_days'), 365)));
    }

    /** A recovery channel is offered only when the feature is built in (config) and switched on here. */
    public static function recoveryEnabled(string $channel): bool
    {
        $built = match ($channel) {
            'recovery_email' => (bool) config('tua.features.recovery_email'),
            'sms' => (bool) config('tua.features.recovery_sms'),
            default => true,
        };

        return $built && (bool) (self::current('recovery')[$channel] ?? false);
    }
}
