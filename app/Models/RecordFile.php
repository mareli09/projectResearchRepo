<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file in one of the five typed slots (CLAUDE.md §7 M2). Stored on the private "research" disk.
 *
 * @property int $id
 * @property int|null $record_id
 * @property int|null $submission_id
 * @property string $slot abstract | full_paper | imrad | toa | references
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property int|null $uploaded_by
 * @property bool $is_current
 */
class RecordFile extends Model
{
    protected $fillable = ['record_id', 'submission_id', 'slot', 'path', 'original_name', 'mime', 'size', 'sha256', 'uploaded_by', 'is_current'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'is_current' => 'boolean'];
    }

    /** @return BelongsTo<ResearchRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'record_id');
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
