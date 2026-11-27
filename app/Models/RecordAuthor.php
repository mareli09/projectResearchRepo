<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $record_id
 * @property string $name
 * @property int|null $user_id
 * @property int $position
 */
class RecordAuthor extends Model
{
    public $timestamps = false;

    protected $fillable = ['record_id', 'name', 'user_id', 'position'];
}
