<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimilarityMatch extends Model
{
    public $timestamps = false;

    protected $fillable = ['check_id', 'record_id', 'score', 'rank', 'shared_terms'];

    protected function casts(): array
    {
        return ['shared_terms' => 'array'];
    }

    /** @return BelongsTo<ResearchRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'record_id');
    }
}
