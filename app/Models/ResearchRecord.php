<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A research output in the repository (CLAUDE.md §6, §7 M2).
 *
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string $abstract
 * @property int $year
 * @property string $research_type
 * @property string $degree_level
 * @property int $academic_unit_id
 * @property int|null $program_id
 * @property int|null $adviser_user_id
 * @property string|null $adviser_name
 * @property string $status draft | published | withdrawn
 * @property Carbon|null $published_at
 * @property int $views
 * @property int $downloads
 * @property string $source
 * @property bool $imrad_not_applicable
 * @property bool $is_complete
 * @property Carbon|null $deleted_at
 */
class ResearchRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'title', 'abstract', 'year', 'research_type', 'degree_level',
        'academic_unit_id', 'program_id', 'adviser_user_id', 'adviser_name', 'source',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'views' => 0,
        'downloads' => 0,
        'source' => 'submission',
        'imrad_not_applicable' => false,
        'is_complete' => false,
        'program_id' => null,
        'adviser_user_id' => null,
        'adviser_name' => null,
        'published_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'published_at' => 'datetime',
            'imrad_not_applicable' => 'boolean',
            'is_complete' => 'boolean',
        ];
    }

    /** @return BelongsTo<AcademicUnit, $this> */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_user_id');
    }

    /** @return HasMany<RecordAuthor, $this> */
    public function authors(): HasMany
    {
        return $this->hasMany(RecordAuthor::class, 'record_id')->orderBy('position');
    }

    /** @return BelongsToMany<Keyword, $this> */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'record_keyword', 'record_id', 'keyword_id')->withPivot('source');
    }

    /** @return BelongsToMany<Sdg, $this> */
    public function sdgs(): BelongsToMany
    {
        return $this->belongsToMany(Sdg::class, 'record_sdg', 'record_id', 'sdg_id')->withPivot('source')->orderBy('sdgs.id');
    }

    /** @return HasMany<RecordFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class, 'record_id');
    }

    /** @return HasMany<RecordFile, $this> */
    public function currentFiles(): HasMany
    {
        return $this->files()->where('is_current', true);
    }

    /** @param  Builder<ResearchRecord>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /** @param  Builder<ResearchRecord>  $query */
    public function scopeMissingKeywords(Builder $query): void
    {
        $query->has('keywords', '<', (int) config('tua.repository.keywords_min'));
    }

    /** @param  Builder<ResearchRecord>  $query */
    public function scopeMissingSdg(Builder $query): void
    {
        $query->doesntHave('sdgs');
    }

    /** @param  Builder<ResearchRecord>  $query */
    public function scopeIncompleteFiles(Builder $query): void
    {
        $query->where('is_complete', false);
    }
}
