<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One K-Means run (CLAUDE.md §7 M6). Becomes "current" only after the URDC confirms it.
 *
 * @property int $id
 * @property string $public_id
 * @property int $k
 * @property float|string|null $silhouette
 * @property float|string|null $davies_bouldin
 * @property int $n_records
 * @property array<string, mixed> $params
 * @property int|null $triggered_by
 * @property string|null $trigger_label
 * @property string $state
 * @property Carbon|null $finished_at
 * @property-read Collection<int, Cluster> $clusters
 */
class ClusterRun extends Model
{
    protected $fillable = ['public_id', 'k', 'silhouette', 'davies_bouldin', 'n_records', 'params', 'triggered_by', 'trigger_label', 'state', 'finished_at'];

    protected function casts(): array
    {
        return ['params' => 'array', 'finished_at' => 'datetime'];
    }

    /** @return HasMany<Cluster, $this> */
    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class)->orderBy('number');
    }

    /** @return list<array<string, mixed>> scores for each k tried (inertia, silhouette, Davies–Bouldin) */
    public function scores(): array
    {
        $scores = $this->params['scores'] ?? [];

        return is_array($scores) ? array_values(array_filter($scores, 'is_array')) : [];
    }
}
