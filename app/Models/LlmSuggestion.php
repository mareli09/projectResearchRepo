<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An LLM suggestion and what the user did with it (CLAUDE.md §10). */
class LlmSuggestion extends Model
{
    protected $fillable = ['kind', 'subject_type', 'subject_id', 'suggested', 'final', 'action', 'rating', 'provider', 'latency_ms', 'fallback_used', 'acted_by', 'acted_at'];

    protected function casts(): array
    {
        return ['fallback_used' => 'boolean', 'acted_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
