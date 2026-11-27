<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $term lowercase
 */
class Keyword extends Model
{
    public $timestamps = false;

    protected $fillable = ['term'];

    /** Keywords are stored lowercase, trimmed, with single spaces. */
    public static function normalize(string $term): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $term)));
    }
}
