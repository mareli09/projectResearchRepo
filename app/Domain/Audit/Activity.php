<?php

namespace App\Domain\Audit;

use LogicException;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Append-only activity log entry (CLAUDE.md §7 M8).
 * No code path may edit or delete an entry through the model.
 * Retention cleanup runs only through `php artisan audit:prune` (years set in System settings).
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Activity log entries cannot be changed.');
        });

        static::deleting(function (): void {
            throw new LogicException('Activity log entries cannot be deleted.');
        });
    }
}
