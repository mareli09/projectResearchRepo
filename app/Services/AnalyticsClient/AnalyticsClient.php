<?php

namespace App\Services\AnalyticsClient;

/**
 * Client for the Python analytics service (CLAUDE.md §11).
 * The service gets text fields only and returns scores, clusters and top terms.
 */
interface AnalyticsClient
{
    /**
     * @param  array<int, array{id: int|string, text: string}>  $records
     * @return array{vectorizer_version: string, n_docs: int, n_terms: int}
     */
    public function fit(array $records): array;

    /** @return array{matches: array<int, array{id: int|string, score: float, shared_terms: string[]}>, top_terms: string[]} */
    public function screen(string $text, int $k = 5): array;

    /** @return array{matches: array<int, array{id: int|string, score: float}>} */
    public function related(int|string $id, int $k = 5): array;

    /** @return array<string, mixed> scores, chosen_k, assignments, clusters (§11) */
    public function cluster(int $kMin = 2, int $kMax = 15, ?int $k = null): array;

    /** @return string[] */
    public function topTerms(string $text, int $n = 5): array;

    /** @return array{ok: bool, vectorizer_version: ?string, n_docs: int, mem_mb: float} */
    public function health(): array;
}
