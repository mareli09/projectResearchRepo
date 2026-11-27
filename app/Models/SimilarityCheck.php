<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimilarityCheck extends Model
{
    protected $fillable = ['submission_id', 'run_at', 'vectorizer_version', 'top_score', 'flag'];

    protected function casts(): array
    {
        return ['run_at' => 'datetime'];
    }

    /** @return HasMany<SimilarityMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(SimilarityMatch::class, 'check_id')->orderBy('rank');
    }
}
