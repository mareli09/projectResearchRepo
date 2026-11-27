<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One K-Means run (CLAUDE.md §7 M6). Becomes "current" only after the URDC confirms it. */
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
}
