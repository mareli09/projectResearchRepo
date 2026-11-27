{{-- Suggestions only: each item needs Accept, Edit or Remove. Nothing is added without a click (CLAUDE.md §10). --}}
@props(['fallback' => false])
<div {{ $attributes->merge(['class' => 'ai-box']) }}>
    <div class="flex items-center gap-2 mb-2">
        <span class="ai-tag">LLM suggestion</span>
        @if ($fallback)<span class="text-xs text-muted">Suggestions unavailable, showing top terms</span>@endif
    </div>
    {{ $slot }}
</div>
