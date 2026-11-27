<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A theme found by a clustering run, with its top terms and label (§7 M6).
 *
 * @property int $id
 * @property int $cluster_run_id
 * @property int $number
 * @property list<string> $top_terms
 * @property string|null $label
 * @property string $label_status
 * @property int $size
 * @property float|string $x
 * @property float|string $y
 */
class Cluster extends Model
{
    protected $fillable = ['cluster_run_id', 'number', 'top_terms', 'label', 'label_status', 'size', 'x', 'y'];

    protected function casts(): array
    {
        return ['top_terms' => 'array'];
    }

    /** @return BelongsTo<ClusterRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ClusterRun::class, 'cluster_run_id');
    }

    /** @return BelongsToMany<ResearchRecord, $this> */
    public function records(): BelongsToMany
    {
        return $this->belongsToMany(ResearchRecord::class, 'record_cluster', 'cluster_id', 'record_id')->withPivot('x', 'y');
    }
}
