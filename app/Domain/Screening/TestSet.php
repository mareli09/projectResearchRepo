<?php

namespace App\Domain\Screening;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\ResearchRecord;
use App\Models\TestPair;
use App\Models\TestPairLabel;
use App\Models\User;
use App\Services\AnalyticsClient\AnalyticsClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The labeled screening test set (CLAUDE.md §7 M5 "AI performance", §17): at least 100
 * study–record pairs, about half duplicates, each labeled by 3 coordinators. A pair's reference
 * label is the one at least 2 coordinators gave. Coordinators label without seeing the score,
 * so the score cannot sway them. Nothing here invents labels: they come only from coordinators.
 */
final class TestSet
{
    public const TARGET = 100;

    public const LABELERS = 3;

    public const MAJORITY = 2;

    /**
     * "duplicate", "distinct", or null while undecided.
     *
     * @param  Collection<int, TestPairLabel>  $labels
     */
    public static function majority(Collection $labels): ?string
    {
        $counts = $labels->countBy('label');
        foreach (['duplicate', 'distinct'] as $l) {
            if (($counts[$l] ?? 0) >= self::MAJORITY) {
                return $l;
            }
        }

        return null;
    }

    /** @return array{pairs: int, decided: int, duplicates: int, distinct: int, unscored: int, labels: int} */
    public static function stats(): array
    {
        $pairs = TestPair::with('labels')->get();
        $decided = $pairs->map(fn (TestPair $p) => self::majority($p->labels))->filter();

        return [
            'pairs' => $pairs->count(),
            'decided' => $decided->count(),
            'duplicates' => $decided->filter(fn ($l) => $l === 'duplicate')->count(),
            'distinct' => $decided->filter(fn ($l) => $l === 'distinct')->count(),
            'unscored' => $pairs->whereNull('score')->count(),
            'labels' => $pairs->sum(fn (TestPair $p) => $p->labels->count()),
        ];
    }

    /**
     * Decided pairs with their score, for the confusion matrix.
     *
     * @return list<array{submission: string, record: ?string, score: float, duplicate: bool}>
     */
    public static function decidedPairs(): array
    {
        return TestPair::with(['labels', 'record:id,public_id'])->whereNotNull('score')->orderBy('id')->get()
            ->map(fn (TestPair $p) => ['pair' => $p, 'label' => self::majority($p->labels)])
            ->filter(fn ($x) => $x['label'] !== null)
            ->map(fn ($x) => [
                'submission' => $x['pair']->public_id,
                'record' => $x['pair']->record->public_id ?? null,
                'score' => round((float) $x['pair']->score, 3),
                'duplicate' => $x['label'] === 'duplicate',
            ])->values()->all();
    }

    /** The next pair this coordinator has not labeled and that still needs labels; random order. */
    public static function nextFor(User $user): ?TestPair
    {
        return self::open($user)->shuffle()->first();
    }

    public static function labeledBy(User $user): int
    {
        return TestPairLabel::where('user_id', $user->id)->count();
    }

    public static function waitingFor(User $user): int
    {
        return self::open($user)->count();
    }

    /** @return Collection<int, TestPair> pairs this user can still label: not labeled by them, undecided, fewer than 3 labels */
    private static function open(User $user): Collection
    {
        return TestPair::with(['labels', 'record', 'leftRecord'])->get()
            ->filter(fn (TestPair $p) => ! $p->labels->contains('user_id', $user->id)
                && $p->labels->count() < self::LABELERS && self::majority($p->labels) === null)
            ->values();
    }

    public static function label(TestPair $pair, User $user, string $label): void
    {
        if (! in_array($label, ['duplicate', 'distinct'], true)) {
            throw new RuntimeException('A label is duplicate or distinct.');
        }
        TestPairLabel::updateOrCreate(['test_pair_id' => $pair->id, 'user_id' => $user->id], ['label' => $label]);
        Audit::record(AuditEvent::REVIEWED, "Test pair {$pair->public_id} labeled {$label}", $pair, ['label' => $label], $user);
    }

    /**
     * Adds pairs from the repository for coordinators to label: half nearest neighbours (similar
     * studies, the hard cases) and half random pairs (clearly distinct). Prepared near-duplicates
     * come in through import().
     *
     * @return int pairs added
     */
    public static function sample(int $n, ?User $by, AnalyticsClient $analytics): int
    {
        $records = ResearchRecord::published()->with('keywords:id,term')->inRandomOrder()->limit(max(2, $n * 2))->get();
        if ($records->count() < 2) {
            throw new RuntimeException('At least 2 published records are needed.');
        }
        $existing = TestPair::get(['left_record_id', 'record_id'])->map(fn ($p) => $p->left_record_id.'-'.$p->record_id)->flip()->all();
        $added = 0;
        $neighbours = intdiv($n + 1, 2);

        foreach ($records as $left) {
            if ($added >= $n) {
                break;
            }
            $source = $added < $neighbours ? 'sample_neighbour' : 'sample_random';
            if ($source === 'sample_neighbour') {
                try {
                    $match = $analytics->related($left->id, 1)['matches'][0] ?? null;
                } catch (Throwable $e) {
                    throw new RuntimeException('The analytics service is not reachable, so similar pairs cannot be found: '.Str::limit($e->getMessage(), 120));
                }
                $rightId = $match ? (int) $match['id'] : null;
            } else {
                $rightId = (int) $records->where('id', '!=', $left->id)->random()->id;
            }
            if ($rightId === null || isset($existing[$left->id.'-'.$rightId])) {
                continue;
            }
            self::create([
                'left_record_id' => $left->id, 'left_title' => $left->title, 'left_abstract' => (string) $left->abstract,
                'left_keywords' => $left->keywords->pluck('term')->all(), 'record_id' => $rightId, 'source' => $source,
            ], $by, $analytics);
            $existing[$left->id.'-'.$rightId] = 1;
            $added++;
        }
        Audit::record(AuditEvent::IMPORT, "Test set: {$added} sample pairs added from the repository", null, ['added' => $added], $by);

        return $added;
    }

    /**
     * Prepared pairs from a CSV: title, abstract, keywords (separated by ;), record (TUA-YYYY-NNNN).
     *
     * @param  list<array<int, string|null>>  $rows  without the header row
     * @return array{added: int, errors: list<string>}
     */
    public static function import(array $rows, User $by, AnalyticsClient $analytics): array
    {
        $errors = [];
        $added = 0;
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            [$title, $abstract, $keywords, $publicId] = array_map(fn ($v) => trim(strip_tags((string) $v)), array_pad($row, 4, ''));
            $record = ResearchRecord::published()->where('public_id', $publicId)->first();
            if ($title === '' || $abstract === '') {
                $errors[] = "Row {$line}: the title and abstract are required.";
            } elseif ($record === null) {
                $errors[] = "Row {$line}: there is no published record {$publicId}.";
            } else {
                self::create([
                    'left_record_id' => null, 'left_title' => mb_substr($title, 0, 500), 'left_abstract' => mb_substr($abstract, 0, 5000),
                    'left_keywords' => array_values(array_filter(array_map('trim', explode(';', Str::lower($keywords))))),
                    'record_id' => $record->id, 'source' => 'import',
                ], $by, $analytics);
                $added++;
            }
        }
        Audit::record(AuditEvent::IMPORT, "Test set: {$added} pairs imported", null, ['added' => $added, 'errors' => count($errors)], $by);

        return ['added' => $added, 'errors' => $errors];
    }

    /** Scores the pairs that have no score yet (for example when the service was down). @return int scored */
    public static function scoreMissing(AnalyticsClient $analytics): int
    {
        $n = 0;
        foreach (TestPair::whereNull('score')->get() as $pair) {
            if (self::score($pair, $analytics)) {
                $n++;
            }
        }

        return $n;
    }

    /** @param array<string, mixed> $data */
    private static function create(array $data, ?User $by, AnalyticsClient $analytics): TestPair
    {
        $pair = DB::transaction(function () use ($data, $by): TestPair {
            $max = (int) TestPair::query()->selectRaw("MAX(CAST(SUBSTRING_INDEX(public_id, '-', -1) AS UNSIGNED)) AS n")->value('n');

            return TestPair::create($data + ['public_id' => sprintf('TP-%03d', $max + 1), 'created_by' => $by?->id]);
        });
        self::score($pair, $analytics);

        return $pair;
    }

    private static function score(TestPair $pair, AnalyticsClient $analytics): bool
    {
        try {
            $r = $analytics->pair(['title' => $pair->left_title, 'abstract' => $pair->left_abstract, 'keywords' => $pair->left_keywords], $pair->record_id);
            $pair->update(['score' => $r['score'], 'shared_terms' => $r['shared_terms'], 'vectorizer_version' => $r['vectorizer_version']]);

            return true;
        } catch (Throwable) {
            return false; // stays unscored; "Score pairs" retries it
        }
    }
}
