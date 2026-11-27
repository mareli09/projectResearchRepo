<?php

namespace App\Domain\Llm;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Models\LlmSuggestion;
use App\Models\User;
use App\Services\AnalyticsClient\AnalyticsClient;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\OpenAiLlmClient;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * LLM suggestions with the TF-IDF fallback (CLAUDE.md §10). The LLM only suggests: nothing is
 * stored on a record until a person accepts it, and every suggestion and action is logged.
 */
final class Suggestions
{
    public function __construct(private readonly LlmClient $llm, private readonly AnalyticsClient $analytics) {}

    /**
     * @return array{source: string, keywords: list<string>, sdgs: list<int>, research_type: ?string, provider: string, latency_ms: ?int, message: ?string}
     */
    public function metadata(string $title, string $abstract): array
    {
        $result = $this->llm->suggestMetadata($title, $abstract);
        $latency = $this->llm instanceof OpenAiLlmClient ? $this->llm->lastLatencyMs() : null;

        if ($result !== null) {
            return [
                'source' => 'llm', 'keywords' => $result['keywords'], 'sdgs' => $result['sdgs'], 'research_type' => $result['research_type'],
                'provider' => $this->llm->name(), 'latency_ms' => $latency, 'message' => null,
            ];
        }

        // Fallback: the top TF-IDF terms of the text. No SDG or type suggestions.
        try {
            $terms = $this->analytics->topTerms(['title' => $title, 'abstract' => $abstract, 'keywords' => []], 5);
        } catch (Throwable) {
            $terms = self::localTopTerms($title.' '.$abstract);
        }

        return [
            'source' => 'fallback', 'keywords' => array_slice($terms, 0, 5), 'sdgs' => [], 'research_type' => null,
            'provider' => $this->llm->name(), 'latency_ms' => $latency, 'message' => 'Suggestions unavailable, showing top terms',
        ];
    }

    /**
     * Label for a cluster: the LLM's suggestion, or the top 3 terms when it is unavailable.
     *
     * @param  list<string>  $topTerms
     * @param  list<string>  $nearestTitles
     * @return array{label: string, fallback: bool, provider: string, latency_ms: ?int}
     */
    public function themeLabel(array $topTerms, array $nearestTitles): array
    {
        $result = $this->llm->suggestThemeLabel($topTerms, $nearestTitles);
        $latency = $this->llm instanceof OpenAiLlmClient ? $this->llm->lastLatencyMs() : null;

        return $result !== null
            ? ['label' => $result['label'], 'fallback' => false, 'provider' => $this->llm->name(), 'latency_ms' => $latency]
            : ['label' => self::termsLabel($topTerms), 'fallback' => true, 'provider' => $this->llm->name(), 'latency_ms' => $latency];
    }

    /** @param list<string> $topTerms */
    public static function termsLabel(array $topTerms): string
    {
        return ucfirst(implode(', ', array_slice(array_values(array_filter($topTerms, fn ($t) => ! str_contains($t, ' '))), 0, 3)) ?: implode(', ', array_slice($topTerms, 0, 3)));
    }

    /**
     * Saves what the user did with each metadata suggestion on a submission (§10 "Logging").
     *
     * @param  list<array{kind?: string, suggested?: string, final?: ?string, action?: string}>  $log
     * @param  array{source?: string, provider?: string, latency_ms?: ?int}  $meta
     */
    public static function record(Model $subject, User $by, array $log, array $meta): int
    {
        $count = 0;
        foreach ($log as $item) {
            $kind = in_array($item['kind'] ?? '', ['keyword', 'sdg', 'research_type'], true) ? $item['kind'] : null;
            $action = in_array($item['action'] ?? '', ['accepted', 'edited', 'rejected', 'pending'], true) ? $item['action'] : 'pending';
            $suggested = mb_substr(trim((string) ($item['suggested'] ?? '')), 0, 190);
            if ($kind === null || $suggested === '') {
                continue;
            }
            LlmSuggestion::create([
                'kind' => $kind,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'suggested' => $suggested,
                'final' => $action === 'edited' ? mb_substr(trim((string) ($item['final'] ?? '')), 0, 190) : ($action === 'accepted' ? $suggested : null),
                'action' => $action,
                'provider' => mb_substr((string) ($meta['provider'] ?? 'unknown'), 0, 40),
                'latency_ms' => isset($meta['latency_ms']) ? (int) $meta['latency_ms'] : null,
                'fallback_used' => ($meta['source'] ?? 'llm') === 'fallback',
                'acted_by' => $action === 'pending' ? null : $by->id,
                'acted_at' => $action === 'pending' ? null : now(),
            ]);
            $count++;
            if ($action !== 'pending') {
                Audit::record(AuditEvent::LLM, ucfirst(str_replace('_', ' ', $kind))." suggestion \"{$suggested}\" {$action}", $subject, [
                    'kind' => $kind, 'action' => $action, 'final' => $item['final'] ?? null, 'fallback' => ($meta['source'] ?? '') === 'fallback',
                ], $by);
            }
        }

        return $count;
    }

    /** @return list<string> */
    private static function localTopTerms(string $text): array
    {
        $stop = array_flip(['the', 'and', 'for', 'with', 'using', 'this', 'that', 'study', 'research', 'from', 'into', 'among', 'are', 'was', 'were', 'its', 'their', 'system']);
        $counts = [];
        foreach (preg_split('/[^a-z]+/', strtolower($text)) ?: [] as $w) {
            if (strlen($w) > 3 && ! isset($stop[$w])) {
                $counts[$w] = ($counts[$w] ?? 0) + 1;
            }
        }
        arsort($counts);

        return array_slice(array_keys($counts), 0, 5);
    }
}
