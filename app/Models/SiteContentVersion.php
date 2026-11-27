<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * [Not in manuscript] One version of the landing page content (CLAUDE.md §8), table site_contents.
 *
 * @property int $version
 * @property array<string, mixed> $data
 * @property string $status draft | published
 * @property string|null $summary
 * @property Carbon|null $published_at
 */
class SiteContentVersion extends Model
{
    protected $table = 'site_contents';

    protected $fillable = ['version', 'data', 'status', 'summary', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
