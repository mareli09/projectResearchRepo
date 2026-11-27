{{-- "Theme labels" (CLAUDE.md §7 M6, §10): accept, edit or reject each LLM label and rate it 1–4. --}}
@php $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3'; @endphp
<x-layouts.app title="Theme labels">
    <x-page-head :eyebrow="'M6 · Theme labels'.($run ? ' · '.$run->public_id : '')" title="Confirm theme labels"
        lead="After each clustering run, the LLM suggests a short label from the 10 top terms of each cluster and the titles of the 5 studies closest to its centre. Accept it, edit it, or reject it to fall back to the top terms. Your rating of each suggestion (1–4) is used to evaluate the LLM feature.">
        <x-slot:actions><span class="ai-tag">LLM suggestion</span></x-slot:actions>
    </x-page-head>

    @if (! $run)
        <x-empty-state title="No clustering run yet">Labels appear here after the URDC runs clustering.</x-empty-state>
    @else
        <div class="stack">
            @foreach ($clusters as $c)
                @php $s = $suggestions[$c->id] ?? null; $confirmed = $c->label_status === 'confirmed'; @endphp
                <x-panel>
                    <div class="ph">
                        <div><span class="mono" style="color: var(--gold)">C{{ $c->number }}</span> <b>{{ $c->label }}</b> <span class="text-muted small">· {{ number_format($c->size) }} studies</span></div>
                        @if ($confirmed)<x-pill variant="ok">{{ ['accepted' => 'Accepted', 'edited' => 'Edited and confirmed', 'rejected' => 'Top terms used'][$s?->action] ?? 'Confirmed' }}</x-pill>
                        @else<x-pill variant="warn">Waiting for you</x-pill>@endif
                    </div>
                    <div class="g2 mt-2">
                        <div>
                            <div class="eyebrow">Top terms (TF-IDF centroid weights)</div>
                            <p class="mono small mt-1">{{ implode(' · ', $c->top_terms) }}</p>
                            <div class="eyebrow mt-2">Studies closest to the centre</div>
                            <ul class="small text-muted mt-1 pl-4 list-disc">@foreach ($nearest[$c->id] ?? [] as $t)<li>{{ $t }}</li>@endforeach</ul>
                        </div>
                        <x-llm-suggestion :fallback="(bool) $s?->fallback_used">
                            <div class="ph"><span class="eyebrow">Suggested label</span></div>
                            <b>{{ $s?->suggested ?? $c->label }}</b>
                            @if ($confirmed)
                                <p class="small text-muted">In use: <b>{{ $c->label }}</b>@if ($s?->rating) · rating {{ str_repeat('★', $s->rating).str_repeat('☆', 4 - $s->rating) }} ({{ $s->rating }} of 4)@endif</p>
                            @else
                                <form method="POST" action="{{ route('themes.label', $c) }}" class="flex flex-col gap-2" novalidate>
                                    @csrf
                                    <div class="flex flex-col gap-1"><label for="lb-{{ $c->id }}" class="font-medium small">Label (for “Save my edit”, 5 words or fewer)</label>
                                        <input id="lb-{{ $c->id }}" name="label" class="{{ $in }}" value="{{ old('label', $s?->suggested ?? $c->label) }}"></div>
                                    <div class="flex flex-col gap-1"><label for="rt-{{ $c->id }}" class="font-medium small">How well does the suggestion describe the studies?</label>
                                        <select id="rt-{{ $c->id }}" name="rating" class="{{ $in }}"><option value="4">4 · Very well</option><option value="3">3 · Well</option><option value="2">2 · Poorly</option><option value="1">1 · Very poorly</option></select></div>
                                    <x-form-error name="label" /><x-form-error name="rating" />
                                    <div class="flex flex-wrap gap-2">
                                        <x-button type="submit" name="decision" value="accept" size="sm" variant="ok">Accept</x-button>
                                        <x-button type="submit" name="decision" value="edit" size="sm" variant="primary">Save my edit</x-button>
                                        <x-button type="submit" name="decision" value="reject" size="sm">Reject, use top terms</x-button>
                                    </div>
                                </form>
                            @endif
                        </x-llm-suggestion>
                    </div>
                </x-panel>
            @endforeach
        </div>
    @endif
</x-layouts.app>
