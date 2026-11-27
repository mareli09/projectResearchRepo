<?php

namespace App\Domain\Repository;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\Keyword;
use App\Models\RecordAuthor;
use App\Models\ResearchRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates and edits records, publishes and withdraws them (CLAUDE.md §7 M2).
 * Every metadata edit is logged with old and new values.
 */
final class RecordWriter
{
    /**
     * @param  array<string, mixed>  $data  validated and normalized (RecordRules)
     */
    public function create(array $data, string $source, string $tagSource = 'user', ?string $publicId = null): ResearchRecord
    {
        return DB::transaction(function () use ($data, $source, $tagSource, $publicId): ResearchRecord {
            $record = new ResearchRecord($this->columns($data));
            $record->source = $source;
            $record->public_id = $publicId ?? self::nextPublicId((int) $data['year']);
            $record->save();
            $this->syncRelations($record, $data, $tagSource);

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated and normalized (RecordRules)
     */
    public function update(ResearchRecord $record, array $data, ?User $by): ResearchRecord
    {
        $old = self::snapshot($record);

        DB::transaction(function () use ($record, $data): void {
            $record->fill($this->columns($data))->save();
            $this->syncRelations($record, $data, 'user');
        });

        $new = self::snapshot($record->refresh());
        $changedOld = array_diff_assoc(array_map('json_encode', $old), array_map('json_encode', $new));
        if ($changedOld !== []) {
            $keys = array_keys($changedOld);
            Audit::record(
                AuditEvent::METADATA_EDIT,
                'Metadata edited: '.implode(', ', $keys),
                $record,
                ['public_id' => $record->public_id],
                $by,
                old: array_intersect_key($old, $changedOld),
                new: array_intersect_key($new, $changedOld),
            );
        }

        return $record;
    }

    /** @return list<string> reasons the record cannot be published; empty when it can */
    public static function publishBlockers(ResearchRecord $record): array
    {
        $reasons = [];
        foreach (FileSlots::missingSlots($record) as $slot) {
            $reasons[] = 'Missing file: '.FileSlots::slots()[$slot]['label'].'.';
        }
        $kw = $record->keywords()->count();
        if ($kw < RepositorySettings::keywordsMin() || $kw > RepositorySettings::keywordsMax()) {
            $reasons[] = 'Needs '.RepositorySettings::keywordsMin().' to '.RepositorySettings::keywordsMax()." keywords (has {$kw}).";
        }
        if (RepositorySettings::wordCount($record->abstract) > RepositorySettings::abstractWordLimit()) {
            $reasons[] = 'The abstract is over the word limit.';
        }

        return $reasons;
    }

    public function publish(ResearchRecord $record, ?User $by): void
    {
        $blockers = self::publishBlockers($record);
        if ($blockers !== []) {
            throw new RuntimeException(implode(' ', $blockers));
        }

        $record->status = 'published';
        $record->published_at ??= now();
        $record->save();
        // Sprint 4–6: index for search and queue for the next clustering run (§7 M3).
        Audit::record(AuditEvent::PUBLISHED, 'Record published', $record, ['public_id' => $record->public_id], $by);
    }

    /** Withdraw = status withdrawn + soft delete. Files and history are kept. */
    public function withdraw(ResearchRecord $record, string $reason, ?User $by): void
    {
        $old = $record->status;
        $record->status = 'withdrawn';
        $record->save();
        $record->delete();
        Audit::record(AuditEvent::METADATA_EDIT, 'Record withdrawn', $record, ['public_id' => $record->public_id, 'reason' => $reason], $by,
            old: ['status' => $old], new: ['status' => 'withdrawn']);
    }

    /** TUA-{year}-{NNNN}, numbered across the whole repository. */
    public static function nextPublicId(int $year): string
    {
        $max = ResearchRecord::withTrashed()
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(public_id, '-', -1) AS UNSIGNED)) AS n")
            ->value('n');

        return sprintf('TUA-%d-%04d', $year, ((int) $max) + 1);
    }

    /** @return array<string, mixed> */
    public static function snapshot(ResearchRecord $record): array
    {
        $record->loadMissing('authors', 'keywords', 'sdgs');

        return [
            'title' => $record->title,
            'abstract' => $record->abstract,
            'year' => $record->year,
            'research_type' => $record->research_type,
            'degree_level' => $record->degree_level,
            'academic_unit_id' => $record->academic_unit_id,
            'program_id' => $record->program_id,
            'adviser_name' => $record->adviser_name,
            'authors' => $record->authors->pluck('name')->all(),
            'keywords' => $record->keywords->pluck('term')->sort()->values()->all(),
            'sdgs' => $record->sdgs->pluck('id')->sort()->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $adviserName = $data['adviser_name'] ?? null;
        $adviserId = $adviserName
            ? User::where('name', $adviserName)->limit(2)->pluck('id')
            : collect();

        return [
            'title' => $data['title'],
            'abstract' => $data['abstract'],
            'year' => (int) $data['year'],
            'research_type' => $data['research_type'],
            'degree_level' => $data['degree_level'],
            'academic_unit_id' => (int) $data['academic_unit_id'],
            'program_id' => $data['program_id'] !== null ? (int) $data['program_id'] : null,
            'adviser_name' => $adviserName,
            // Link to a user account only when exactly one user has that name.
            'adviser_user_id' => $adviserId->count() === 1 ? $adviserId->first() : null,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function syncRelations(ResearchRecord $record, array $data, string $tagSource): void
    {
        RecordAuthor::where('record_id', $record->id)->delete();
        foreach (array_values($data['authors']) as $i => $name) {
            RecordAuthor::create(['record_id' => $record->id, 'name' => $name, 'position' => $i + 1]);
        }

        $keywordIds = [];
        foreach ($data['keywords'] as $term) {
            $keywordIds[Keyword::firstOrCreate(['term' => Keyword::normalize($term)])->id] = ['source' => $tagSource];
        }
        $record->keywords()->sync($keywordIds);

        $record->sdgs()->sync(array_fill_keys(array_map('intval', $data['sdgs'] ?? []), ['source' => $tagSource]));
        $record->unsetRelation('authors')->unsetRelation('keywords')->unsetRelation('sdgs');
    }
}
