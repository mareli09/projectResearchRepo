@php
    use App\Domain\Repository\ResearchTypes;
    $editable = ! $record->trashed();
    $sel = 'min-h-11 rounded-md border border-line bg-surface px-3 w-full';
    $programsByUnit = $units->mapWithKeys(fn ($u) => [$u->id => $u->programs->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()]);
    $oldKeywords = old('keywords', $record->keywords->pluck('term')->implode(', '));
    $oldSdgs = array_map('intval', (array) old('sdgs', $record->sdgs->pluck('id')->all()));
@endphp
<x-layouts.app :title="$record->public_id">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <div class="eyebrow"><a href="{{ route('urdc.records') }}">Records and metadata</a> · <span class="mono">{{ $record->public_id }}</span></div>
            <h1>{{ $record->title }}</h1>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($record->trashed())<x-pill variant="bad">Withdrawn</x-pill>
                @elseif ($record->status === 'published')<x-pill variant="ok">Published {{ $record->published_at?->format('M j, Y') }}</x-pill>
                @else<x-pill>Draft</x-pill>@endif
                <x-pill>{{ ResearchTypes::SOURCES[$record->source] }}</x-pill>
                <span class="text-[13px] text-muted self-center">{{ number_format($record->views) }} views · {{ number_format($record->downloads) }} downloads</span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
        {{-- Metadata --}}
        <x-panel title="Metadata">
            <form method="POST" action="{{ route('urdc.records.update', $record) }}" class="flex flex-col gap-4" novalidate
                  x-data="{ unit: @js((string) old('academic_unit_id', $record->academic_unit_id)), programs: @js($programsByUnit), words: 0, count(t) { this.words = (t.trim().match(/\S+/g) || []).length } }">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $editable) class="flex flex-col gap-4">
                    <x-input name="title" label="Title" :value="$record->title" required />

                    <div class="flex flex-col gap-1">
                        <label for="f-abstract" class="font-medium">Abstract</label>
                        <textarea id="f-abstract" name="abstract" rows="8" required x-init="count($el.value)" @input="count($el.value)"
                            class="w-full rounded-md border px-3 py-2 bg-surface {{ $errors->has('abstract') ? 'border-bad' : 'border-line' }}">{{ old('abstract', $record->abstract) }}</textarea>
                        <p class="text-[13px]" :class="words > {{ $wordLimit }} ? 'text-bad' : 'text-muted'"><span x-text="words"></span> / {{ $wordLimit }} words</p>
                        <x-form-error name="abstract" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="f-authors" class="font-medium">Authors</label>
                        <textarea id="f-authors" name="authors" rows="3" class="w-full rounded-md border border-line bg-surface px-3 py-2" aria-describedby="f-authors-hint">{{ old('authors', $record->authors->pluck('name')->implode("\n")) }}</textarea>
                        <p id="f-authors-hint" class="text-[13px] text-muted">One name per line.</p>
                        <x-form-error name="authors" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="adviser_name" label="Adviser" :value="$record->adviser_name" />
                        <x-input name="year" type="number" label="Year" :value="$record->year" inputmode="numeric" required />
                        <div class="flex flex-col gap-1">
                            <label for="f-unit" class="font-medium">College</label>
                            <select id="f-unit" name="academic_unit_id" x-model="unit" class="{{ $sel }}">
                                @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->code }} · {{ $u->name }}</option>@endforeach
                            </select>
                            <x-form-error name="academic_unit_id" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="f-program" class="font-medium">Program</label>
                            <select id="f-program" name="program_id" class="{{ $sel }}">
                                <option value="">—</option>
                                <template x-for="p in (programs[unit] || [])" :key="p.id">
                                    <option :value="p.id" x-text="p.name" :selected="String(p.id) === @js((string) old('program_id', $record->program_id))"></option>
                                </template>
                            </select>
                            <x-form-error name="program_id" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="f-type" class="font-medium">Research type</label>
                            <select id="f-type" name="research_type" class="{{ $sel }}">
                                @foreach (ResearchTypes::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('research_type', $record->research_type) === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="f-degree" class="font-medium">Degree level</label>
                            <select id="f-degree" name="degree_level" class="{{ $sel }}">
                                @foreach (ResearchTypes::DEGREE_LEVELS as $k => $l)<option value="{{ $k }}" @selected(old('degree_level', $record->degree_level) === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="f-keywords" class="font-medium">Keywords</label>
                        <input id="f-keywords" name="keywords" value="{{ $oldKeywords }}" aria-describedby="f-keywords-hint"
                            class="w-full min-h-11 rounded-md border px-3 bg-surface {{ $errors->has('keywords') ? 'border-bad' : 'border-line' }}">
                        <p id="f-keywords-hint" class="text-[13px] text-muted">{{ config('tua.repository.keywords_min') }} to {{ config('tua.repository.keywords_max') }} keywords, separated by commas.</p>
                        <x-form-error name="keywords" />
                    </div>

                    <fieldset>
                        <legend class="font-medium mb-1">SDG alignment</legend>
                        <div class="grid gap-1 sm:grid-cols-2">
                            @foreach ($sdgs as $sdg)
                                <label class="flex items-center gap-2 min-h-9 text-[13px]">
                                    <input type="checkbox" name="sdgs[]" value="{{ $sdg->id }}" @checked(in_array($sdg->id, $oldSdgs, true)) class="size-4">
                                    <span><span class="mono">{{ $sdg->id }}</span> {{ $sdg->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-form-error name="sdgs" />
                    </fieldset>

                    @if ($editable)
                        <div><x-button type="submit" variant="primary">Save metadata</x-button>
                            <p class="text-[13px] text-muted mt-1">Every edit is logged with the old and new values.</p></div>
                    @endif
                </fieldset>
            </form>
        </x-panel>

        {{-- Files, publish, withdraw --}}
        <div class="flex flex-col gap-4">
            <x-panel title="Files">
                <div class="flex flex-col gap-3">
                    @foreach ($slots as $key => $def)
                        <x-file-slot :name="$key" :def="$def" :file="$files[$key] ?? null" :older="$older[$key] ?? []" :record="$record"
                            :na="$key === 'imrad' && $record->imrad_not_applicable" :editable="$editable" :max-mb="$maxMb" />
                        @if ($key === 'imrad' && $editable)
                            <form method="POST" action="{{ route('urdc.records.imrad', $record) }}" class="-mt-1">
                                @csrf
                                <input type="hidden" name="not_applicable" value="{{ $record->imrad_not_applicable ? 0 : 1 }}">
                                <x-button type="submit" size="sm">{{ $record->imrad_not_applicable ? 'IMRAD version is required' : 'Mark IMRAD not applicable' }}</x-button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </x-panel>

            @if ($editable && $record->status !== 'published')
                <x-panel title="Publish">
                    @if ($blockers)
                        <x-callout variant="gold">
                            <b>Not ready to publish:</b>
                            <ul class="list-disc pl-5 mt-1">@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul>
                        </x-callout>
                    @else
                        <form method="POST" action="{{ route('urdc.records.publish', $record) }}">
                            @csrf
                            <x-button type="submit" variant="ok">Publish</x-button>
                        </form>
                    @endif
                    <x-form-error name="publish" class="mt-2" />
                </x-panel>
            @endif

            @if ($editable)
                <x-panel title="Withdraw">
                    <form method="POST" action="{{ route('urdc.records.withdraw', $record) }}" class="flex flex-col gap-2" novalidate>
                        @csrf
                        <x-input name="reason" label="Reason" required />
                        <x-button type="submit" variant="bad">Withdraw record</x-button>
                        <p class="text-[13px] text-muted">The record leaves the catalog. Its files and history are kept.</p>
                    </form>
                </x-panel>
            @endif
        </div>
    </div>
</x-layouts.app>
