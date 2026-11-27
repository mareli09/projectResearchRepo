<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One coordinator's label for a test pair: duplicate or distinct.
 *
 * @property int $id
 * @property int $test_pair_id
 * @property int $user_id
 * @property string $label
 */
class TestPairLabel extends Model
{
    protected $fillable = ['test_pair_id', 'user_id', 'label'];

    /** @return BelongsTo<TestPair, $this> */
    public function pair(): BelongsTo
    {
        return $this->belongsTo(TestPair::class, 'test_pair_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
