<x-layouts.app :title="$sub ? 'Review '.$sub['id'] : 'Review submission'">
    @if (! $sub)
        <x-page-head eyebrow="Review submission" title="Nothing to review" />
        <x-empty-state title="No submissions from your advisees yet">You get an email when one arrives.</x-empty-state>
    @else
        <x-page-head :eyebrow="'Review submission · '.$sub['id']" :title="$sub['title']">
            <x-slot:actions><x-status-pill :status="$sub['status']" /></x-slot:actions>
        </x-page-head>
        <div class="split">
            <div class="stack">
                <x-panel title="Abstract">
                    <p>{{ $sub['abstract'] }}</p>
                    <div class="chips mt-2.5">@foreach ($sub['keywords'] as $k)<span class="chip">{{ $k }}</span>@endforeach @foreach ($sub['sdg'] as $s)<span class="chip">SDG {{ $s }}</span>@endforeach</div>
                    <div class="meta mt-2.5"><span>{{ $sub['type'] }}</span><span>{{ $sub['program'] }}</span><span>Submitted by {{ $sub['student'] }}</span></div>
                </x-panel>
                <x-panel title="Files">
                    <div class="flex flex-col gap-1.5">
                        @foreach ($slots as $key => $def)
                            @php $f = $files[$key] ?? null; @endphp
                            <div class="slot {{ $f ? 'filled' : '' }}"><span>{{ $def['label'] }}</span>
                                @if ($f)<a class="small" href="{{ \App\Http\Controllers\RecordFileController::signedUrl($f) }}">Open {{ $f->original_name }}</a>
                                @elseif ($key === 'imrad' && $sub['model']->imrad_not_applicable)<x-pill>Not applicable</x-pill>
                                @else<x-pill variant="warn">Missing</x-pill>@endif</div>
                        @endforeach
                    </div>
                </x-panel>
                <x-panel title="Screening result">
                    @if ($sub['screened'])<x-slot:actions><x-flag-pill :score="$sub['score']" /></x-slot:actions>
                    @endif
                    @if ($matches)<x-match-table :matches="$matches" />@else @include('partials.screening-callout', ['sub' => $sub]) @endif
                </x-panel>
            </div>
            <x-panel title="Your decision">
                @if ($can)
                    <form method="POST" action="{{ route('adviser.decide') }}" class="flex flex-col gap-2.5" novalidate>
                        @csrf
                        <input type="hidden" name="id" value="{{ $sub['id'] }}">
                        <p class="small text-muted">Endorsing sends the study to the research coordinator of its cluster.</p>
                        <div class="flex flex-col gap-1"><label for="adv-reason" class="font-medium">Comment (required when returning)</label>
                            <textarea id="adv-reason" name="reason" rows="4" class="w-full rounded-md border bg-surface px-3 py-2 {{ $errors->has('reason') ? 'border-bad' : 'border-line' }}" placeholder="What should the student fix?">{{ old('reason') }}</textarea>
                            <x-form-error name="reason" /></div>
                        <div class="flex flex-wrap gap-2">
                            <x-button type="submit" name="decision" value="endorse" variant="ok">Endorse</x-button>
                            <x-button type="submit" name="decision" value="return" variant="bad">Return with reason</x-button>
                        </div>
                    </form>
                @else
                    <p class="small text-muted">No action needed. Current status: <x-status-pill :status="$sub['status']" /></p>
                @endif
                <h3 class="font-semibold mt-4">Team</h3>
                <ul class="small">@foreach ($sub['members'] as $m)<li>{{ $m }}</li>@endforeach</ul>
                <h3 class="font-semibold mt-4">History</h3>
                <div class="list small">@foreach ($sub['history'] as $h)<div><b>{{ $h[0] }}</b> · {{ $h[1] }} <span class="text-muted">· {{ $h[2] }}</span>@if ($h[3])<div>“{{ $h[3] }}”</div>@endif</div>@endforeach</div>
            </x-panel>
        </div>
    @endif
</x-layouts.app>
