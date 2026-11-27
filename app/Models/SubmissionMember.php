<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['submission_id', 'name', 'user_id', 'position'];
}
