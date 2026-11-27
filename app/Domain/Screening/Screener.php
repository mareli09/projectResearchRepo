<?php

namespace App\Domain\Screening;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\ResearchRecord;
use App\Models\Setting;
use App\Models\SimilarityCheck;
use App\Models\Submission;
use App\Services\AnalyticsClient\AnalyticsClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

/**
 * Similarity screening (CLAUDE.md §7 M5): compares a submission with every published record
 * (TF-IDF + cosine similarity in the Python service) and stores the top 5 with their scores and
 * shared terms. It never rejects anything: the coordinator always decides.
 */
final class Screener
{
    public function __construct(private readonly AnalyticsClient $client) {}

    /**
     * Refit the TF-IDF index on all published records (§7 M5: after clustering runs or every 50 new records).
     *
     * @return array{vectorizer_version: string, n_docs: int, n_terms: int}
     */
    public function fit(): array
    {
        $records = ResearchRecord::published()->with('keywords:id,term')->get(['id', 'title', 'abstract'])
            ->map(fn (ResearchRecord $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'abstract' => $r->abstract,
                'keywords' => $r->keywords->pluck('term')->all(),
            ])->values()->all();

        $started = microtime(true);
        $result = $this->client->fit($records);
        Setting::updateOrCreate(['key' => 'screening.index'], ['value' => [
            'vectorizer_version' => $result['vectorizer_version'],
            'n_docs' => $result['n_docs'],
            'n_terms' => $result['n_terms'],
            'fitted_at' => now()->toIso8601String(),
            'published_since_fit' => 0,
        ]]);
        Audit::record(AuditEvent::SERVICE, 'Analytics service: TF-IDF index refitted', null, [
            'service' => 'analytics', 'purpose' => 'fit', 'fields_sent' => ['title', 'abstract', 'keywords'],
            'n_docs' => $result['n_docs'], 'ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $result;
    }

    /** Screens a submission and stores the result. Refits once if the service has no index yet. */
    public function screen(Submission $sub): SimilarityCheck
    {
        $fields = ['title' => $sub->title, 'abstract' => $sub->abstract, 'keywords' => $sub->keywords];
        $started = microtime(true);

        try {
            $result = $this->client->screen($fields, (int) config('tua.screening.top_k'));
        } catch (RequestException $e) {
            if ($e->response->status() !== 409) {
                throw $e;
            }
            $this->fit();
            $result = $this->client->screen($fields, (int) config('tua.screening.top_k'));
        }

        $published = ResearchRecord::published()->whereIn('id', collect($result['matches'])->pluck('id'))->pluck('id')->all();
        $matches = array_values(array_filter($result['matches'], fn ($m) => in_array((int) $m['id'], $published, true)));
        $top = (float) ($matches[0]['score'] ?? 0.0);
        $flag = Thresholds::flag($top);

        $check = DB::transaction(function () use ($sub, $matches, $top, $flag, $result): SimilarityCheck {
            $check = SimilarityCheck::create([
                'submission_id' => $sub->id,
                'run_at' => now(),
                'vectorizer_version' => $result['vectorizer_version'] ?? null,
                'top_score' => round($top, 3),
                'flag' => $flag,
            ]);
            foreach ($matches as $rank => $m) {
                $check->matches()->create([
                    'record_id' => (int) $m['id'],
                    'score' => round((float) $m['score'], 3),
                    'rank' => $rank + 1,
                    'shared_terms' => $m['shared_terms'],
                ]);
            }
            $sub->forceFill(['max_similarity' => round($top, 3), 'flag' => $flag])->save();

            return $check;
        });

        Audit::record(AuditEvent::SERVICE, "Analytics service: screened {$sub->public_id} (".number_format($top, 2).', '.Thresholds::label($flag).')', $sub, [
            'service' => 'analytics', 'purpose' => 'screen', 'fields_sent' => array_keys($fields),
            'top_score' => round($top, 3), 'flag' => $flag, 'ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $check;
    }

    /** Called after a record is published: refit after every N new records (setting, default 50). */
    public static function notePublished(): bool
    {
        $state = (array) Setting::valueOf('screening.index', []);
        $since = (int) ($state['published_since_fit'] ?? 0) + 1;
        Setting::updateOrCreate(['key' => 'screening.index'], ['value' => array_merge($state, ['published_since_fit' => $since])]);

        return empty($state['vectorizer_version']) || $since >= (int) config('tua.clustering.refit_after_records');
    }
}
