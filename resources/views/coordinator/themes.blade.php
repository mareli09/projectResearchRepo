{{-- "Theme labels" (CLAUDE.md §7 M6, §10): accept, edit or reject each LLM label and rate it 1–4. --}}
@php $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3'; @endphp
<x-layouts.app title="Theme labels">
    <x-page-head :eyebrow="'M6 · Theme labels · '.$run['id']" title="Confirm theme labels"
        lead="After each clustering run, the LLM suggests a short label from the 10 top terms of each cluster and the titles of the 5 studies closest to its centre. Accept it, edit it, or reject it to fall back to the top terms. Your rating of each suggestion (1–4) is used to evaluate the LLM feature.">
        <x-slot:actions><span class="ai-tag">LLM suggestion</span></x-slot:actions>
    </x-page-head>
    <x-preview-note sprint="6" what="The labels come from a sample clustering run, and Accept, Save and Reject" />

    <div class="stack">
        @foreach ($labels as $l)
            <x-panel>
                <div class="ph">
                    <div><span class="mono" style="color: var(--gold)">C{{ $l['id'] }}</span> <b>{{ $l['label'] }}</b> <span class="text-muted small">· {{ number_format($counts[$l['id']] ?? 0) }} studies</span></div>
                    @if ($l['confirmed'])<x-pill variant="ok">{{ $l['action'] === 'edited' ? 'Edited and confirmed' : ($l['action'] === 'rejected' ? 'Top terms used' : 'Accepted') }}</x-pill>
                    @else<x-pill variant="warn">Waiting for you</x-pill>@endif
                </div>
                <div class="g2 mt-2">
                    <div>
                        <div class="eyebrow">Top terms (TF-IDF centroid weights)</div>
                        <p class="mono small mt-1">{{ implode(' · ', $l['terms']) }}</p>
                        <div class="eyebrow mt-2">Closest studies sent with the terms</div>
                        <ul class="small text-muted mt-1 pl-4 list-disc">@foreach ($nearest[$l['id']] ?? [] as $t)<li>{{ $t }}</li>@endforeach</ul>
                    </div>
                    <x-llm-suggestion>
                        <div class="ph"><span class="eyebrow">Suggested label</span></div>
                        <b>{{ $l['llm'] }}</b>
                        @if ($l['confirmed'])
                            <p class="small text-muted">Rating: {{ $l['rating'] ? str_repeat('★', $l['rating']).str_repeat('☆', 4 - $l['rating']).' ('.$l['rating'].' of 4)' : 'not rated' }}</p>
                        @else
                            <div class="flex flex-col gap-1"><label for="lb-{{ $l['id'] }}" class="font-medium small">Label to confirm</label><input id="lb-{{ $l['id'] }}" class="{{ $in }}" value="{{ $l['llm'] }}"></div>
                            <div class="flex flex-col gap-1"><label for="rt-{{ $l['id'] }}" class="font-medium small">How well does the suggestion describe the studies?</label>
                                <select id="rt-{{ $l['id'] }}" class="{{ $in }}"><option>4 · Very well</option><option>3 · Well</option><option>2 · Poorly</option><option>1 · Very poorly</option></select></div>
                            <div class="flex flex-wrap gap-2">
                                <x-button size="sm" variant="ok" preview="Sprint 6">Accept</x-button>
                                <x-button size="sm" variant="primary" preview="Sprint 6">Save my edit</x-button>
                                <x-button size="sm" preview="Sprint 6">Reject, use top terms</x-button>
                            </div>
                        @endif
                    </x-llm-suggestion>
                </div>
            </x-panel>
        @endforeach
    </div>
</x-layouts.app>
