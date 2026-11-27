<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'record_id', 'record_file_id', 'slot', 'ip_address', 'user_agent'];
}
