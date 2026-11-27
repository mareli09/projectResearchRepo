<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One workflow transition; return and reject always carry a reason (CLAUDE.md §1 rule 5). */
class SubmissionReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['submission_id', 'step', 'reviewer_user_id', 'decision', 'reason', 'created_at'];

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
