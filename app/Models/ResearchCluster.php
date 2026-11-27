<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchCluster extends Model
{
    protected $fillable = ['number', 'name', 'coordinator_user_id'];

    /** @return HasMany<AcademicUnit, $this> */
    public function academicUnits(): HasMany
    {
        return $this->hasMany(AcademicUnit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }
}
