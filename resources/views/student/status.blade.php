<x-layouts.app title="Submission status">
    @if (! $sub)
        <x-page-head eyebrow="M3 · Status tracking" title="Submission status" />
        <x-empty-state title="No submissions yet">Your submissions and their progress appear here.
            <x-slot:action><x-button :href="route('student.submit')" variant="primary">Submit a study</x-button></x-slot:action>
        </x-empty-state>
    @else
        <x-page-head :eyebrow="'M3 · Status tracking · '.$sub['id']" :title="$sub['title']">
            @if ($mine->count() > 1)
                <x-slot:actions>
                    <form method="GET" class="flex items-end gap-2">
                        <div class="flex flex-col gap-1"><label for="st-pick" class="text-[13px] font-medium">Submission</label>
                            <select id="st-pick" name="id" data-autosubmit class="min-h-11 rounded-md border border-line bg-surface px-3">
                                @foreach ($mine as $m)<option value="{{ $m->public_id }}" @selected($m->public_id === $sub['id'])>{{ $m->public_id }} · {{ ucfirst($m->status) }}</option>@endforeach
                            </select></div>
                        <noscript><x-button type="submit">Open</x-button></noscript>
                    </form>
                </x-slot:actions>
            @endif
        </x-page-head>

        <x-panel class="mb-4"><x-stepper :sub="$sub" />
            @if ($sub['record'])<x-callout variant="ok" class="mt-3">Published as <a class="mono" href="{{ route('studies.show', $sub['record']) }}">{{ $sub['record'] }}</a>. It can now be found in search.</x-callout>@endif
        </x-panel>

        <div class="split">
            <div class="stack">
                <x-panel title="Similarity screening result">
                    @if ($sub['screened'])<x-slot:actions><x-flag-pill :score="$sub['score']" /></x-slot:actions>
                    @endif
                    <p class="small text-muted mb-2.5">Title, abstract and keywords compared with all approved studies (TF-IDF, cosine similarity). The coordinator decides. Nothing is rejected automatically.</p>
                    @if ($matches)<x-match-table :matches="$matches" />@else @include('partials.screening-callout', ['sub' => $sub]) @endif
                </x-panel>

                <x-panel title="Files">
                    <div class="flex flex-col gap-1.5">
                        @foreach ($slots as $key => $def)
                            @php $f = $files[$key] ?? null; @endphp
                            <div class="slot {{ $f ? 'filled' : '' }}">
                                <span>{{ $def['label'] }}</span>
                                @if ($f)<a class="small" href="{{ \App\Http\Controllers\RecordFileController::signedUrl($f) }}">{{ $f->original_name }}</a>
                                @elseif ($key === 'imrad' && $sub['model']->imrad_not_applicable)<x-pill>Not applicable</x-pill>
                                @else<x-pill variant="warn">Missing</x-pill>@endif
                            </div>
                        @endforeach
                    </div>
                </x-panel>
            </div>

            <x-panel title="History">
                <div class="list">
                    @foreach ($sub['history'] as $h)
                        <div><div class="ph"><b>{{ $h[0] }}</b><span class="text-muted small">{{ $h[2] }}</span></div>
                            <div class="small text-muted">{{ $h[1] }}</div>
                            @if ($h[3])<p class="small mt-1">“{{ $h[3] }}”</p>@endif</div>
                    @endforeach
                </div>
                <p class="small text-muted mt-2.5">You get an email at every step. <a href="{{ route('notifications') }}">See your notifications</a>.</p>

                @if ($sub['status'] === 'Returned')
                    <form method="POST" action="{{ route('student.resubmit') }}" enctype="multipart/form-data" class="callout callout-bad mt-3 flex flex-col gap-2.5" novalidate>
                        @csrf
                        <input type="hidden" name="id" value="{{ $sub['id'] }}">
                        <div><b>Returned for revision.</b> Fix what the reviewer asked, upload the corrected files, then resubmit. It goes back to your adviser.</div>
                        @foreach ($slots as $key => $def)
                            <div class="flex flex-col gap-1"><label for="rs-{{ $key }}" class="small font-medium">New version of {{ $def['label'] }} (optional)</label>
                                <input id="rs-{{ $key }}" type="file" name="files[{{ $key }}]" accept="{{ implode(',', array_map(fn ($e) => '.'.$e, $def['mimes'])) }}" class="text-[13px]">
                                <x-form-error :name="'files.'.$key" /></div>
                        @endforeach
                        <x-form-error name="files" />
                        <div class="flex flex-col gap-1"><label for="rs-note" class="small font-medium">What did you change?</label>
                            <textarea id="rs-note" name="note" rows="2" class="w-full rounded-md border border-line bg-surface px-3 py-2">{{ old('note') }}</textarea></div>
                        <div><x-button type="submit" variant="primary">Resubmit to my adviser</x-button></div>
                    </form>
                @endif
            </x-panel>
        </div>
    @endif
</x-layouts.app>
