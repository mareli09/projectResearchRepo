<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicUnit extends Model
{
    protected $fillable = ['code', 'name', 'research_cluster_id'];

    /** @return BelongsTo<ResearchCluster, $this> */
    public function researchCluster(): BelongsTo
    {
        return $this->belongsTo(ResearchCluster::class);
    }

    /** @return HasMany<Program, $this> */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
