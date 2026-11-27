<?php

namespace App\Services\AnalyticsClient;

/**
 * Client for the Python analytics service (CLAUDE.md §11).
 * The service gets text fields only (title, abstract, keywords) and returns scores, clusters and top terms.
 */
interface AnalyticsClient
{
    /**
     * @param  list<array{id: int|string, title: string, abstract: string, keywords: list<string>}>  $records
     * @return array{vectorizer_version: string, n_docs: int, n_terms: int}
     */
    public function fit(array $records): array;

    /**
     * @param  array{title: string, abstract: string, keywords: list<string>}  $fields
     * @return array{matches: list<array{id: int|string, score: float, shared_terms: list<string>}>, top_terms: list<string>, vectorizer_version: ?string}
     */
    public function screen(array $fields, int $k = 5, int|string|null $excludeId = null): array;

    /** @return array{matches: list<array{id: int|string, score: float}>} */
    public function related(int|string $id, int $k = 5): array;

    /** @return array<string, mixed> scores, chosen_k, assignments, clusters (§11) */
    public function cluster(int $kMin = 2, int $kMax = 15, ?int $k = null): array;

    /**
     * @param  array{title?: string, abstract?: string, keywords?: list<string>}  $fields
     * @return list<string>
     */
    public function topTerms(array $fields, int $n = 5): array;

    /** @return array{ok: bool, vectorizer_version: ?string, n_docs: int, mem_mb: float} */
    public function health(): array;
}
