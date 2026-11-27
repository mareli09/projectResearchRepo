{{-- Study page (signed in) and study abstract (guest), CLAUDE.md §7 M4. --}}
@php
    use App\Domain\Repository\FileSlots;
    use App\Domain\Repository\ResearchTypes;
    $me = auth()->user();
    $isStudent = $me?->hasRole(\App\Models\User::ROLE_STUDENT);
    $show = fn ($r) => $public ? route('catalog.show', $r->public_id) : route('studies.show', $r->public_id);
@endphp
<x-dynamic-component :component="$public ? 'layouts.site' : 'layouts.app'" :title="$record->public_id">
    <div @class(['ls-in' => $public]) @if ($public) style="padding-block:32px" @endif>
        <x-page-head :eyebrow="$record->public_id.' · '.ResearchTypes::TYPES[$record->research_type]" :title="$record->title">
            <x-slot:actions><x-button :href="$public ? route('catalog') : route('search')">Back to {{ $public ? 'catalog' : 'search' }}</x-button></x-slot:actions>
        </x-page-head>

        <div class="split">
            <div class="stack">
                <x-panel title="Abstract">
                    <p>{{ $record->abstract }}</p>
                    <div class="chips mt-3">
                        @foreach ($record->keywords as $k)<span class="chip">{{ $k->term }}</span>@endforeach
                        @foreach ($record->sdgs as $s)<span class="chip">SDG {{ $s->id }}</span>@endforeach
                    </div>
                </x-panel>

                <x-panel title="Files">
                    <x-slot:actions>
                        <span class="text-muted small">
                            @if ($public) Sign in to request the full paper
                            @elseif ($isStudent) Full papers are released when the URDC approves a request
                            @else Files you may open are listed with a download link @endif
                        </span>
                    </x-slot:actions>
                    <div class="tbl">
                        <table>
                            <thead><tr><th>Slot</th><th>Type</th><th class="r">Size</th><th>SHA-256</th><th></th></tr></thead>
                            <tbody>
                                @foreach (FileSlots::slots() as $slot => $def)
                                    @php $f = $files[$slot] ?? null; @endphp
                                    <tr>
                                        <td>{{ $def['label'] }}</td>
                                        <td>{{ $f ? strtoupper(pathinfo($f->original_name, PATHINFO_EXTENSION)) : strtoupper($def['mimes'][0]) }}</td>
                                        <td class="r num">{{ $f ? number_format($f->size / 1024).' KB' : '—' }}</td>
                                        <td class="mono small">{{ $f ? substr($f->sha256, 0, 12).'…' : '—' }}</td>
                                        <td class="r">
                                            @if ($public)
                                                <x-button :href="route('login')" size="sm">Sign in</x-button>
                                            @elseif ($f && $me?->can('download', $f))
                                                <x-button :href="\App\Http\Controllers\RecordFileController::signedUrl($f)" size="sm">Download</x-button>
                                            @elseif ($isStudent && $f && in_array($slot, \App\Domain\Repository\AccessRequests::SLOTS, true))
                                                <a href="#request-access" class="small">{{ $access?->status === 'pending' ? 'Request pending' : 'Request access' }}</a>
                                            @elseif (! $f)
                                                <span class="text-muted small">Not uploaded</span>
                                            @else
                                                <span class="text-muted small">Not available to your role</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($isStudent)
                        <div id="request-access" class="mt-3">
                            @if ($access?->status === 'pending')
                                <x-callout>Your request <span class="mono">{{ $access->public_id }}</span> is waiting for the URDC. You get an email when it is decided.</x-callout>
                            @elseif ($access?->status === 'approved' && ($access->expires_at === null || $access->expires_at->isFuture()))
                                <x-callout variant="ok">Request <span class="mono">{{ $access->public_id }}</span> approved. You can download the full paper until {{ $access->expires_at?->format('F j, Y') }}. Downloads are logged.</x-callout>
                            @else
                                @if ($access?->status === 'declined')
                                    <x-callout variant="bad" class="mb-3">Request <span class="mono">{{ $access->public_id }}</span> was declined. Reason: {{ $access->reason }}</x-callout>
                                @elseif ($access?->status === 'approved')
                                    <x-callout class="mb-3">Your access from request <span class="mono">{{ $access->public_id }}</span> ended on {{ $access->expires_at?->format('F j, Y') }}.</x-callout>
                                @endif
                                <form method="POST" action="{{ route('studies.request', $record->public_id) }}" class="flex flex-col gap-2" novalidate>
                                    @csrf
                                    <label for="purpose" class="font-medium">Request the full paper</label>
                                    <p class="small text-muted" id="purpose-hint">Say what you need it for, for example a related-literature review for your own thesis. The URDC approves requests following University policy.</p>
                                    <textarea id="purpose" name="purpose" rows="3" aria-describedby="purpose-hint" class="w-full rounded-md border border-line bg-surface px-3 py-2">{{ old('purpose') }}</textarea>
                                    <x-form-error name="purpose" />
                                    <div><x-button type="submit" variant="primary">Send request</x-button></div>
                                </form>
                            @endif
                        </div>
                    @endif
                </x-panel>

                <x-panel title="Related studies">
                    <x-slot:actions><span class="text-muted small">TF-IDF cosine similarity · shared keywords if the analytics service is down</span></x-slot:actions>
                    <div class="list">
                        @forelse ($related as $x)
                            <div>
                                <div class="ph"><a href="{{ $show($x['record']) }}">{{ $x['record']->title }}</a><span class="mono small">{{ number_format($x['score'], 2) }}</span></div>
                                <div class="meta"><span>{{ $x['record']->academicUnit->code }}</span><span>{{ $x['record']->year }}</span><span>{{ ResearchTypes::TYPES[$x['record']->research_type] }}</span></div>
                            </div>
                        @empty
                            <p class="text-muted">No related studies yet.</p>
                        @endforelse
                    </div>
                </x-panel>
            </div>

            <x-panel title="Details">
                <dl class="grid grid-cols-[auto_1fr] gap-x-3.5 gap-y-2 text-[13px]">
                    <dt class="text-muted">Authors</dt><dd>{{ $record->authors->pluck('name')->implode(', ') }}</dd>
                    <dt class="text-muted">Adviser</dt><dd>{{ $record->adviser_name ?? '—' }}</dd>
                    <dt class="text-muted">College</dt><dd>{{ $record->academicUnit->name }}</dd>
                    <dt class="text-muted">Program</dt><dd>{{ $record->program?->name ?? '—' }}</dd>
                    <dt class="text-muted">Degree level</dt><dd>{{ ResearchTypes::DEGREE_LEVELS[$record->degree_level] }}</dd>
                    <dt class="text-muted">Year</dt><dd>{{ $record->year }}</dd>
                    <dt class="text-muted">Theme</dt><dd>{{ $theme ? \App\Domain\Site\Corpus::themeLabels()[$theme] : '—' }}</dd>
                    <dt class="text-muted">SDG</dt><dd>{{ $record->sdgs->map(fn ($s) => 'SDG '.$s->id.' · '.$s->name)->implode('; ') ?: '—' }}</dd>
                    <dt class="text-muted">Views</dt><dd class="num">{{ number_format($record->views) }}</dd>
                    <dt class="text-muted">Downloads</dt><dd class="num">{{ number_format($record->downloads) }}</dd>
                </dl>
                @if ($me?->can('records.manage'))
                    <x-button :href="route('urdc.records.edit', $record)" size="sm" class="mt-4">Edit metadata</x-button>
                @endif
            </x-panel>
        </div>
    </div>
</x-dynamic-component>
