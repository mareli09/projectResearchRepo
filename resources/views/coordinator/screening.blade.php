{{-- "Similarity review" (CLAUDE.md §7 M3, M5): side-by-side comparison with shared terms highlighted, then the decision. --}}
@php
    $shared ??= [];
    $mark = function (string $text) use ($shared): string {
        $out = e($text);
        foreach ($shared as $w) {
            $out = preg_replace('/\b('.preg_quote(e($w), '/').')\b/iu', '<mark>$1</mark>', $out) ?? $out;
        }

        return $out;
    };
    $in = 'w-full min-h-11 rounded-md border bg-surface px-3';
@endphp
<x-layouts.app :title="$sub ? 'Similarity review '.$sub['id'] : 'Similarity review'">
    @if (! $sub)
        <x-page-head eyebrow="M5 · Similarity review" title="Nothing to review" />
        <x-empty-state title="No endorsed submissions in your cluster">They appear here after the adviser endorses them, and you get an email.</x-empty-state>
    @else
        <x-page-head :eyebrow="'M5 · Similarity review · '.$sub['id']" title="Compare with existing studies">
            <x-slot:actions><x-status-pill :status="$sub['status']" /></x-slot:actions>
        </x-page-head>

        <x-panel title="Top 5 matches" class="mb-4">
            @if ($matches)
                <x-slot:actions><span class="text-muted small">Choose a study to compare it side by side</span></x-slot:actions>
                <x-match-table :matches="$matches" :select-url="route('coordinator.screening', ['id' => $sub['id'], 'match' => '__i__'])" :selected="$idx" />
            @else
                @include('partials.screening-callout', ['sub' => $sub])
            @endif
        </x-panel>

        <div class="side-by-side mb-4">
            <x-panel>
                <div class="eyebrow">New submission · {{ $sub['id'] }}</div>
                <h2 class="mt-1 mb-2.5">{!! $mark($sub['title']) !!}</h2>
                <p class="small">{!! $mark($sub['abstract']) !!}</p>
                <div class="chips mt-2.5">@foreach ($sub['keywords'] as $k)<span class="chip">{{ $k }}</span>@endforeach</div>
                <p class="small text-muted mt-2.5">{{ $sub['student'] }} · {{ $sub['program'] }} · Adviser: {{ $sub['adviser'] }}</p>
            </x-panel>
            @if ($match)
                <x-panel>
                    <div class="ph"><div class="eyebrow">Match #{{ $idx + 1 }} · {{ $match->public_id }} · {{ $match->year }}</div><x-score-bar :score="$score" /></div>
                    <h2 class="mt-1 mb-2.5">{!! $mark($match->title) !!}</h2>
                    <p class="small">{!! $mark($match->abstract) !!}</p>
                    <div class="chips mt-2.5">@foreach ($match->keywords as $k)<span class="chip">{{ $k->term }}</span>@endforeach</div>
                    <p class="small text-muted mt-2.5">{{ $match->authors->pluck('name')->implode(', ') }} · {{ $match->academicUnit->code }} · Adviser: {{ $match->adviser_name }}</p>
                </x-panel>
            @else
                <x-empty-state title="No match to compare">The closest existing studies appear here once screening runs.</x-empty-state>
            @endif
        </div>

        <x-panel title="Your decision">
            <x-slot:actions><span class="text-muted small">Shared terms: @forelse ($shared as $w)<mark>{{ $w }}</mark> @empty none @endforelse</span></x-slot:actions>
            @if ($can)
                <form method="POST" action="{{ route('coordinator.decide') }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    <input type="hidden" name="id" value="{{ $sub['id'] }}">
                    @if ($match)<input type="hidden" name="matched_record_id" value="{{ $match->id }}">@endif
                    <div class="form-grid">
                        <div class="flex flex-col gap-1"><label for="co-dec" class="font-medium">Decision</label>
                            <select id="co-dec" name="decision" class="{{ $in }} border-line">
                                <option value="clear" @selected(old('decision') === 'clear')>Original enough, send to URDC</option>
                                <option value="revise" @selected(old('decision') === 'revise')>Needs revision, return to student</option>
                                <option value="reject" @selected(old('decision') === 'reject')>Confirmed duplicate, reject</option>
                            </select></div>
                        <div class="flex flex-col gap-1"><label for="co-reason" class="font-medium">Reason (saved as labeled data for recalibration)</label>
                            <textarea id="co-reason" name="reason" rows="3" class="w-full rounded-md border bg-surface px-3 py-2 {{ $errors->has('reason') ? 'border-bad' : 'border-line' }}" placeholder="Explain how the scope, data or method differs, or why it duplicates.">{{ old('reason') }}</textarea>
                            <x-form-error name="reason" /></div>
                    </div>
                    <p class="small text-muted">A reason is required, except when you clear a submission screened as original.</p>
                    <div><x-button type="submit" variant="primary">Record decision</x-button></div>
                </form>
            @else
                <p class="text-muted small">No decision needed now. Current status: <x-status-pill :status="$sub['status']" /></p>
            @endif
        </x-panel>
    @endif
</x-layouts.app>
