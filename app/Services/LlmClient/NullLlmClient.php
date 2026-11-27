<?php

namespace App\Services\LlmClient;

/**
 * Used while LLM_ENABLED=false or before a provider and DPO approval exist.
 * Always returns null, so callers show "Suggestions unavailable, showing top terms".
 */
class NullLlmClient implements LlmClient
{
    public function suggestMetadata(string $title, string $abstract): ?array
    {
        return null;
    }

    public function suggestThemeLabel(array $topTerms, array $nearestTitles): ?array
    {
        return null;
    }

    public function name(): string
    {
        return 'none';
    }
}
