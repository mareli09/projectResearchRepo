<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One pair of the labeled screening test set (CLAUDE.md §7 M5).
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $left_record_id
 * @property string $left_title
 * @property string $left_abstract
 * @property list<string> $left_keywords
 * @property int $record_id
 * @property float|string|null $score
 * @property list<string>|null $shared_terms
 * @property string|null $vectorizer_version
 * @property string $source sample_neighbour | sample_random | import
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
class TestPair extends Model
{
    protected $fillable = ['public_id', 'left_record_id', 'left_title', 'left_abstract', 'left_keywords', 'record_id', 'score', 'shared_terms', 'vectorizer_version', 'source', 'created_by'];

    protected function casts(): array
    {
        return ['left_keywords' => 'array', 'shared_terms' => 'array'];
    }

    /** @return BelongsTo<ResearchRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'record_id');
    }

    /** @return BelongsTo<ResearchRecord, $this> */
    public function leftRecord(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'left_record_id');
    }

    /** @return HasMany<TestPairLabel, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(TestPairLabel::class);
    }
}
