<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Runtime settings the URDC or System administrator can change (CLAUDE.md §7 M9). */
class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function valueOf(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->find($key);

        return $setting === null ? $default : $setting->value;
    }
}
