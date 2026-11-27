<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sdg extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'name'];
}
