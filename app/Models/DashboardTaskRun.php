<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One evaluator's attempt at one dashboard task (CLAUDE.md §7 M7 "Dashboard task mode").
 *
 * @property int $id
 * @property int $user_id
 * @property int $task_no
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $status started | done | skipped
 * @property string|null $answer
 */
class DashboardTaskRun extends Model
{
    protected $fillable = ['user_id', 'task_no', 'started_at', 'finished_at', 'status', 'answer'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'task_no' => 'integer'];
    }

    /** Seconds from start to finish, measured on the server; null while the task is still open. */
    public function seconds(): ?int
    {
        return $this->finished_at ? (int) $this->started_at->diffInSeconds($this->finished_at, true) : null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
