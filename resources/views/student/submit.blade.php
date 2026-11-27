{{-- "Submit a study" (CLAUDE.md §7 M2, M3). LLM suggestions (§10) arrive in Sprint 6. --}}
@php
    use App\Domain\Repository\ResearchTypes;
    $u = auth()->user();
    $in = 'w-full min-h-11 rounded-md border bg-surface px-3';
    $err = fn ($k) => $errors->has($k) ? 'border-bad' : 'border-line';
    $state = ['accepted' => 'Accepted', 'edited' => 'Edited', 'rejected' => 'Removed'];
    $oldSdgs = array_map('intval', (array) old('sdgs', []));
@endphp
<x-layouts.app title="Submit a study">
    <x-page-head eyebrow="M2 · M3 · Submission form" title="Submit a study" lead="Metadata is captured here so your study can be searched, screened and counted in reports. Fields marked with * are required." />

    @if ($errors->any())
        <x-callout variant="bad" class="mb-4" role="alert"><b>Please fix the {{ $errors->count() === 1 ? 'item' : $errors->count().' items' }} below.</b> Your files need to be attached again.</x-callout>
    @endif

    <form method="POST" action="{{ route('student.store') }}" enctype="multipart/form-data" class="stack" novalidate
          x-data="{ w: 0, imradNa: @js((bool) old('imrad_not_applicable')) }">
        @csrf
        <x-panel title="Study details">
            <div class="form-grid">
                <div class="full flex flex-col gap-1"><label for="sb-title" class="font-medium">Title *</label>
                    <input id="sb-title" name="title" value="{{ old('title') }}" required class="{{ $in }} {{ $err('title') }}"><x-form-error name="title" /></div>
                <div class="full flex flex-col gap-1" x-init="w = ($refs.a.value.trim().match(/\S+/g) || []).length">
                    <label for="sb-abs" class="font-medium">Abstract * ({{ \App\Domain\Repository\RepositorySettings::abstractWordLimit() }} words max) <span class="text-muted" x-text="'· ' + w + ' words'"></span></label>
                    <textarea id="sb-abs" name="abstract" x-ref="a" rows="6" required class="w-full rounded-md border bg-surface px-3 py-2 {{ $err('abstract') }}" @input="w = ($el.value.trim().match(/\S+/g) || []).length">{{ old('abstract') }}</textarea>
                    <x-form-error name="abstract" /></div>
                <div class="flex flex-col gap-1"><label for="sb-type" class="font-medium">Research type *</label>
                    <select id="sb-type" name="research_type" class="{{ $in }} {{ $err('research_type') }}"><option value="">Choose…</option>
                        @foreach (array_slice(ResearchTypes::TYPES, 0, 3, true) as $k => $l)<option value="{{ $k }}" @selected(old('research_type') === $k)>{{ $l }}</option>@endforeach</select>
                    <x-form-error name="research_type" /></div>
                <div class="flex flex-col gap-1"><label for="sb-prog" class="font-medium">Program *</label>
                    <select id="sb-prog" name="program_id" class="{{ $in }} {{ $err('program_id') }}"><option value="">Choose…</option>
                        @foreach ($programs as $p)<option value="{{ $p->id }}" @selected((string) old('program_id', $u->program_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select>
                    <x-form-error name="program_id" /></div>
                <div class="flex flex-col gap-1"><label for="sb-adv" class="font-medium">Adviser *</label>
                    <select id="sb-adv" name="adviser_user_id" class="{{ $in }} {{ $err('adviser_user_id') }}"><option value="">Choose…</option>
                        @foreach ($advisers as $a)<option value="{{ $a->id }}" @selected((string) old('adviser_user_id') === (string) $a->id)>{{ $a->name }}</option>@endforeach</select>
                    <x-form-error name="adviser_user_id" /></div>
                <div class="flex flex-col gap-1"><label for="sb-year" class="font-medium">Year *</label>
                    <input id="sb-year" name="year" type="number" inputmode="numeric" class="{{ $in }} {{ $err('year') }}" value="{{ old('year', date('Y')) }}"><x-form-error name="year" /></div>
                <div class="full flex flex-col gap-1"><label for="sb-auth" class="font-medium">Authors * (one per line)</label>
                    <textarea id="sb-auth" name="authors" rows="3" class="w-full rounded-md border bg-surface px-3 py-2 {{ $err('authors') }}">{{ old('authors', $u->name) }}</textarea>
                    <x-form-error name="authors" /></div>
            </div>
        </x-panel>

        <x-panel title="Suggested metadata">
            <x-slot:actions><span class="ai-tag">LLM suggestion</span></x-slot:actions>
            <p class="small text-muted mb-2.5">Get suggested keywords, SDG tags and research type from your title and abstract. Only the title and abstract are sent, never your files or personal details. Nothing is added until you accept it.</p>
            <x-button preview="Sprint 6" style="border-color: var(--gold)">✦ Suggest from title and abstract</x-button>
            <x-llm-suggestion class="mt-3">
                <p class="small text-muted">Example of how suggestions will look (Sprint 6).</p>
                <div class="chips">
                    @foreach ($suggestion['keywords'] as $k)
                        <span class="sug {{ $k['s'] }}">{{ $k['e'] ?? $k['v'] }}
                            @if (isset($state[$k['s']]))<span class="text-muted small">· {{ $state[$k['s']] }}</span>@else<x-button size="sm" variant="ok" preview="Sprint 6">Accept</x-button><x-button size="sm" preview="Sprint 6">Edit</x-button><x-button size="sm" preview="Sprint 6" aria-label="Remove">✕</x-button>@endif
                        </span>
                    @endforeach
                </div>
            </x-llm-suggestion>
        </x-panel>

        <x-panel title="Keywords and SDGs">
            <div class="flex flex-col gap-1"><label for="sb-kw" class="font-medium">Keywords * (3 to 5, separated by commas)</label>
                <input id="sb-kw" name="keywords" value="{{ old('keywords') }}" placeholder="tf-idf, cosine similarity, title screening" class="{{ $in }} {{ $err('keywords') }}">
                <x-form-error name="keywords" /></div>
            <fieldset class="mt-3.5">
                <legend class="eyebrow mb-1.5">Sustainable Development Goals</legend>
                <div class="chips">@foreach ($sdgs as $s)<label class="chip cursor-pointer min-h-9"><input type="checkbox" name="sdgs[]" value="{{ $s->id }}" @checked(in_array($s->id, $oldSdgs, true))> SDG {{ $s->id }} · {{ $s->name }}</label>@endforeach</div>
                <x-form-error name="sdgs" />
            </fieldset>
        </x-panel>

        <x-panel title="Required files">
            <x-slot:actions><span class="text-muted small">Each file gets a SHA-256 fingerprint. An exact copy of an existing file stops the submission. Max {{ $maxMb }} MB each.</span></x-slot:actions>
            <div class="flex flex-col gap-2">
                @foreach ($slots as $key => $def)
                    <div class="slot {{ $errors->has('files.'.$key) ? 'border-bad' : '' }}" @if ($key === 'imrad') :class="imradNa && 'filled'" @endif>
                        <div class="min-w-0">
                            <label for="sl-{{ $key }}"><b>{{ $def['label'] }}</b> <span class="text-muted small">· {{ strtoupper(implode(' / ', $def['mimes'])) }}</span></label>
                            @if ($key === 'imrad')
                                <label class="flex items-center gap-2 small mt-1"><input type="checkbox" name="imrad_not_applicable" value="1" x-model="imradNa"> Not applicable to my study</label>
                            @endif
                            <x-form-error :name="'files.'.$key" />
                        </div>
                        <input id="sl-{{ $key }}" type="file" name="files[{{ $key }}]" accept="{{ implode(',', array_map(fn ($e) => '.'.$e, $def['mimes'])) }}" class="text-[13px] max-w-64"
                            @if ($key === 'imrad') :disabled="imradNa" @endif>
                    </div>
                @endforeach
            </div>
        </x-panel>

        <div class="flex flex-wrap items-center gap-2">
            <x-button type="submit" variant="primary">Submit for adviser endorsement</x-button>
            <x-button preview="Sprint 3b" data-preview-what="Save draft">Save draft</x-button>
            <span class="small text-muted">Your adviser is emailed as soon as you submit.</span>
        </div>
    </form>
</x-layouts.app>
