<?php

namespace App\Services\LlmClient;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Repository\ResearchTypes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * OpenAI chat completions as the LLM provider (CLAUDE.md §10). Suggestions only.
 *
 * - Sends only the title and abstract (metadata) or the cluster terms and nearest titles (labels).
 *   Never names, emails, files or full papers.
 * - Fixed, versioned prompts that ask for JSON only; the reply is untrusted and validated.
 * - Timeout (default 8 s) and rate limit (default 60/min). Any failure returns null, so the caller
 *   falls back to the top TF-IDF terms.
 * - The key stays in .env on the server. OpenAI does not train on API data by default; the
 *   Data Privacy Officer's approval is still required before production use.
 */
class OpenAiLlmClient implements LlmClient
{
    public const PROMPT_VERSION = 'tua-2026-11-v1';

    private ?int $lastLatency = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 8,
        private readonly int $ratePerMinute = 60,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function name(): string
    {
        return 'openai:'.$this->model;
    }

    public function lastLatencyMs(): ?int
    {
        return $this->lastLatency;
    }

    public function suggestMetadata(string $title, string $abstract): ?array
    {
        $system = 'You help catalogue university research for the TUA Research Repository. '
            .'Reply with JSON only: {"keywords": [3 to 5 lowercase keywords of 1 to 4 words, no duplicates], '
            .'"sdgs": [1 or 2 UN Sustainable Development Goal numbers from 1 to 17], '
            .'"research_type": one of "Undergraduate thesis", "Capstone project", "Graduate thesis", "Faculty research"}. '
            .'Base everything only on the title and abstract given.';
        $user = "Title: {$title}\n\nAbstract: {$abstract}";

        $json = $this->ask($system, $user, 'metadata', ['title', 'abstract']);
        if ($json === null) {
            return null;
        }

        $keywords = [];
        foreach ((array) ($json['keywords'] ?? []) as $k) {
            $k = Str::lower(trim((string) $k));
            $words = preg_split('/\s+/', $k, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($k !== '' && count($words) >= 1 && count($words) <= 4 && mb_strlen($k) <= 60 && ! in_array($k, $keywords, true) && preg_match('/^[\p{L}\p{N}\s\-\.\+#]+$/u', $k)) {
                $keywords[] = $k;
            }
        }
        $keywords = array_slice($keywords, 0, 5);

        $sdgs = [];
        foreach ((array) ($json['sdgs'] ?? []) as $s) {
            if (is_numeric($s) && (int) $s >= 1 && (int) $s <= 17 && ! in_array((int) $s, $sdgs, true)) {
                $sdgs[] = (int) $s;
            }
        }

        $type = ResearchTypes::typeKey(is_string($json['research_type'] ?? null) ? $json['research_type'] : null);

        if (count($keywords) < 1) {
            return null; // nothing usable: fall back
        }

        return ['keywords' => $keywords, 'sdgs' => array_slice($sdgs, 0, 3), 'research_type' => $type];
    }

    public function suggestThemeLabel(array $topTerms, array $nearestTitles): ?array
    {
        $system = 'You name research themes for a university repository. '
            .'Reply with JSON only: {"label": a short theme label of at most 5 words, plain words only, '
            .'no punctuation (the word "and" is allowed)}. Base it only on the terms and titles given.';
        $user = 'Top terms: '.implode(', ', array_slice($topTerms, 0, 10))."\n\nTitles of the 5 closest studies:\n- ".implode("\n- ", array_slice($nearestTitles, 0, 5));

        $json = $this->ask($system, $user, 'theme_label', ['cluster_terms', 'titles']);
        $label = trim((string) ($json['label'] ?? ''));
        $words = preg_split('/\s+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($label === '' || count($words) > 5 || ! preg_match('/^[\p{L}\p{N}\s]+$/u', $label)) {
            return null;
        }

        return ['label' => Str::ucfirst($label)];
    }

    /**
     * @param  list<string>  $fieldsSent  for the activity log only (never the content)
     * @return array<string, mixed>|null
     */
    private function ask(string $system, string $user, string $purpose, array $fieldsSent): ?array
    {
        if (! RateLimiter::attempt('llm-provider', $this->ratePerMinute, fn () => true, 60)) {
            $this->log($purpose, $fieldsSent, 'rate_limited', null);

            return null;
        }

        $started = microtime(true);
        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system.' (prompt '.self::PROMPT_VERSION.')'],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
            $this->lastLatency = (int) ((microtime(true) - $started) * 1000);

            if (! $response->successful()) {
                $this->log($purpose, $fieldsSent, 'http_'.$response->status(), $this->lastLatency);

                return null;
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $json = json_decode($content, true);
            $this->log($purpose, $fieldsSent, is_array($json) ? 'ok' : 'invalid_json', $this->lastLatency);

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            $this->lastLatency = (int) ((microtime(true) - $started) * 1000);
            $this->log($purpose, $fieldsSent, 'unavailable', $this->lastLatency);

            return null;
        }
    }

    /** @param list<string> $fieldsSent */
    private function log(string $purpose, array $fieldsSent, string $result, ?int $ms): void
    {
        Audit::record(AuditEvent::SERVICE, "LLM service: {$purpose} suggestion ({$result})", null, [
            'service' => 'llm', 'provider' => $this->name(), 'purpose' => $purpose,
            'fields_sent' => $fieldsSent, 'result' => $result, 'ms' => $ms, 'prompt' => self::PROMPT_VERSION,
        ]);
    }
}
