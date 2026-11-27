<?php

namespace App\Services\LlmClient;

/**
 * Suggestions only (CLAUDE.md §10). Implementations receive only the title,
 * abstract, keywords or cluster terms. They never write to the database.
 * Return null when the service is off, down or replies with invalid JSON,
 * so the caller can fall back to the top TF-IDF terms.
 */
interface LlmClient
{
    /** @return array{keywords: string[], sdgs: int[], research_type: ?string}|null */
    public function suggestMetadata(string $title, string $abstract): ?array;

    /**
     * @param  string[]  $topTerms  top 10 centroid terms
     * @param  string[]  $nearestTitles  5 titles closest to the centroid
     * @return array{label: string}|null
     */
    public function suggestThemeLabel(array $topTerms, array $nearestTitles): ?array;

    public function name(): string;
}
