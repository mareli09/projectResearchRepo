<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's request for a full paper (CLAUDE.md §7 M4).
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $record_id
 * @property string $purpose
 * @property string $status pending | approved | declined
 * @property int|null $decided_by
 * @property string|null $reason
 * @property Carbon|null $decided_at
 * @property Carbon|null $expires_at
 */
class AccessRequest extends Model
{
    protected $fillable = ['public_id', 'user_id', 'record_id', 'purpose', 'status', 'decided_by', 'reason', 'decided_at', 'expires_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ResearchRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'record_id');
    }
}
