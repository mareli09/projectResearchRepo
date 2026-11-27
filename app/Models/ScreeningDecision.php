<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreeningDecision extends Model
{
    protected $fillable = ['submission_id', 'coordinator_user_id', 'matched_record_id', 'decision', 'reason', 'label_for_calibration'];
}
