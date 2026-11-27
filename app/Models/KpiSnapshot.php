<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['scope_type', 'scope_id', 'metric', 'period', 'value', 'computed_at'];

    protected function casts(): array
    {
        return ['computed_at' => 'datetime'];
    }
}
