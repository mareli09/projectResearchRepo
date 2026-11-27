<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A study moving through the four-step workflow (CLAUDE.md §7 M3).
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $record_id
 * @property int $student_user_id
 * @property int|null $adviser_user_id
 * @property int|null $research_cluster_id
 * @property int|null $academic_unit_id
 * @property int|null $program_id
 * @property string $title
 * @property string $abstract
 * @property string $research_type
 * @property int $year
 * @property list<string> $keywords
 * @property list<int> $sdgs
 * @property bool $imrad_not_applicable
 * @property string $status
 * @property int $current_step
 * @property string|null $max_similarity
 * @property string|null $flag
 * @property Carbon|null $submitted_at
 */
class Submission extends Model
{
    protected $fillable = [
        'public_id', 'record_id', 'student_user_id', 'adviser_user_id', 'research_cluster_id', 'academic_unit_id', 'program_id',
        'title', 'abstract', 'research_type', 'year', 'keywords', 'sdgs', 'imrad_not_applicable', 'status', 'current_step', 'max_similarity', 'flag', 'submitted_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft', 'current_step' => 0, 'imrad_not_applicable' => false,
        'max_similarity' => null, 'flag' => null, 'record_id' => null, 'submitted_at' => null,
    ];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'sdgs' => 'array', 'submitted_at' => 'datetime', 'year' => 'integer', 'current_step' => 'integer', 'imrad_not_applicable' => 'boolean'];
    }

    /** Status labels used on screens (CLAUDE.md §15 wording keys). */
    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }

    /** @return HasMany<RecordFile, $this> */
    public function currentFiles(): HasMany
    {
        return $this->hasMany(RecordFile::class)->where('is_current', true);
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_user_id');
    }

    /** @return BelongsTo<ResearchCluster, $this> */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(ResearchCluster::class, 'research_cluster_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<ResearchRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class, 'record_id');
    }

    /** @return HasMany<SubmissionMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(SubmissionMember::class)->orderBy('position');
    }

    /** @return HasMany<SubmissionReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(SubmissionReview::class)->orderBy('id');
    }

    /** @return HasMany<RecordFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class);
    }

    /** @return HasMany<SimilarityCheck, $this> */
    public function similarityChecks(): HasMany
    {
        return $this->hasMany(SimilarityCheck::class);
    }

    /** @return HasMany<ScreeningDecision, $this> */
    public function screeningDecisions(): HasMany
    {
        return $this->hasMany(ScreeningDecision::class);
    }
}
