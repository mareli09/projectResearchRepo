<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'query', 'filters', 'results_count', 'created_at'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
