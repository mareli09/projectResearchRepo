<x-layouts.app title="Approve and publish">
    <x-page-head eyebrow="M3 · Final step" title="Approve and publish" lead="Publishing creates the record, moves the files to it, indexes it for search and adds it to the next clustering run." />
    <div class="stack">
        @forelse ($queue as $s)
            @php $sf = $files[$s['model']->id] ?? collect(); @endphp
            <x-panel>
                <div class="ph"><div><div class="eyebrow">{{ $s['id'] }} · {{ $s['college'] }} · {{ $s['type'] }}</div><h2 class="mt-1">{{ $s['title'] }}</h2></div><x-status-pill :status="$s['status']" /></div>
                <p class="small my-2">{{ $s['abstract'] }}</p>
                <div class="meta mb-2.5"><span>{{ implode(', ', $s['members']) }}</span><span>Adviser: {{ $s['adviser'] }}</span>
                    <span>{{ $s['screened'] ? 'Highest match '.number_format($s['score'], 2) : 'Not screened yet' }}</span>
                    <span>{{ $sf->count() }} of {{ $s['model']->imrad_not_applicable ? 4 : 5 }} files</span></div>
                <x-callout class="small">Coordinator: “{{ collect($s['history'])->firstWhere(0, 'Reviewed')[3] ?? '' ?: 'No comment' }}”</x-callout>
                <form method="POST" action="{{ route('urdc.decide') }}" class="mt-2.5 flex flex-col gap-2" novalidate>
                    @csrf
                    <input type="hidden" name="id" value="{{ $s['id'] }}">
                    <div class="flex flex-col gap-1"><label for="ur-{{ $s['id'] }}" class="font-medium">Reason (required to return or reject)</label>
                        <textarea id="ur-{{ $s['id'] }}" name="reason" rows="2" class="w-full rounded-md border border-line bg-surface px-3 py-2"></textarea></div>
                    @if ($s['model']->imrad_not_applicable)
                        <label class="flex items-center gap-2 small"><input type="hidden" name="imrad_not_applicable" value="0"><input type="checkbox" name="imrad_not_applicable" value="1" checked> Confirm the IMRAD version is not applicable</label>
                    @endif
                    @if (old('id') === $s['id'])<x-form-error name="reason" />@endif
                    <x-form-error :name="'decision_'.$s['id']" />
                    <div class="flex flex-wrap gap-2">
                        <x-button type="submit" name="decision" value="approve" variant="ok">Approve and publish</x-button>
                        <x-button type="submit" name="decision" value="return">Return</x-button>
                        <x-button type="submit" name="decision" value="reject" variant="bad">Reject</x-button>
                    </div>
                </form>
            </x-panel>
        @empty
            <x-empty-state title="Nothing waiting for approval">Submissions cleared by a research coordinator appear here.</x-empty-state>
        @endforelse
        <x-panel title="Recently published">
            <div class="list">
                @forelse ($published as $s)
                    <div class="ph"><span>{{ $s['title'] }} @if ($s['record'])<a class="mono small" href="{{ route('studies.show', $s['record']) }}">{{ $s['record'] }}</a>@endif</span><span class="text-muted small">{{ last($s['history'])[2] ?? '' }}</span></div>
                @empty
                    <p class="text-muted">Nothing published through the workflow yet.</p>
                @endforelse
            </div>
        </x-panel>
    </div>
</x-layouts.app>
