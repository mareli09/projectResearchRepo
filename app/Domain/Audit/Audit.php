<?php

namespace App\Domain\Audit;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * The one way to write to the activity log (CLAUDE.md §13).
 * Each entry stores time (Asia/Manila), user, event, details, subject,
 * IP address, user agent, and old and new values for edits.
 * Never pass passwords, tokens or codes in $details.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(
        string $event,
        string $description,
        ?Model $subject = null,
        array $details = [],
        ?Authenticatable $causer = null,
        ?array $old = null,
        ?array $new = null,
    ): ?ActivityContract {
        $request = app()->bound('request') ? request() : null;

        $properties = ['details' => $details];
        if ($old !== null) {
            $properties['old'] = $old;
        }
        if ($new !== null) {
            $properties['new'] = $new;
        }
        $properties['ip'] = $request?->ip();
        $properties['user_agent'] = Str::limit((string) $request?->userAgent(), 250, '');

        $logger = activity('audit')->event($event)->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $causer ??= auth()->user();
        if ($causer instanceof User) {
            $logger->causedBy($causer);
        }

        return $logger->log($description);
    }
}
