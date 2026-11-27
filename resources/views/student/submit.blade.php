{{-- "Submit a study" (CLAUDE.md §7 M2, M3) with LLM metadata suggestions (§10). --}}
@php
    use App\Domain\Repository\ResearchTypes;
    $u = auth()->user();
    $in = 'w-full min-h-11 rounded-md border bg-surface px-3';
    $err = fn ($k) => $errors->has($k) ? 'border-bad' : 'border-line';
    $oldSdgs = array_map('intval', (array) old('sdgs', []));
@endphp
<x-layouts.app title="Submit a study">
    <x-page-head eyebrow="M2 · M3 · Submission form" title="Submit a study" lead="Metadata is captured here so your study can be searched, screened and counted in reports. Fields marked with * are required." />

    @if ($errors->any())
        <x-callout variant="bad" class="mb-4" role="alert"><b>Please fix the {{ $errors->count() === 1 ? 'item' : $errors->count().' items' }} below.</b> Your files need to be attached again.</x-callout>
    @endif

    <form method="POST" action="{{ route('student.store') }}" enctype="multipart/form-data" class="stack" novalidate
          x-data="Object.assign(tuaSuggest({ url: @js(route('student.suggest')), csrf: @js(csrf_token()), keywords: @js((string) old('keywords')), sdgs: @js($oldSdgs), type: @js((string) old('research_type')), sdgNames: @js($sdgs->pluck('name', 'id')), types: @js(ResearchTypes::TYPES) }), { w: 0, imradNa: @js((bool) old('imrad_not_applicable')) })">
        @csrf
        <input type="hidden" name="llm_log" :value="log()">
        <input type="hidden" name="llm_meta" :value="meta()">
        <x-panel title="Study details">
            <div class="form-grid">
                <div class="full flex flex-col gap-1"><label for="sb-title" class="font-medium">Title *</label>
                    <input id="sb-title" name="title" value="{{ old('title') }}" required class="{{ $in }} {{ $err('title') }}"><x-form-error name="title" /></div>
                <div class="full flex flex-col gap-1" x-init="w = ($refs.a.value.trim().match(/\S+/g) || []).length">
                    <label for="sb-abs" class="font-medium">Abstract * ({{ \App\Domain\Repository\RepositorySettings::abstractWordLimit() }} words max) <span class="text-muted" x-text="'· ' + w + ' words'"></span></label>
                    <textarea id="sb-abs" name="abstract" x-ref="a" rows="6" required class="w-full rounded-md border bg-surface px-3 py-2 {{ $err('abstract') }}" @input="w = ($el.value.trim().match(/\S+/g) || []).length">{{ old('abstract') }}</textarea>
                    <x-form-error name="abstract" /></div>
                <div class="flex flex-col gap-1"><label for="sb-type" class="font-medium">Research type *</label>
                    <select id="sb-type" name="research_type" x-model="type" class="{{ $in }} {{ $err('research_type') }}"><option value="">Choose…</option>
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
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn" style="border-color: var(--gold)" @click="suggest()" :disabled="loading">
                    <span x-show="!loading">✦ Suggest from title and abstract</span><span x-show="loading" x-cloak>Asking for suggestions…</span>
                </button>
                <span class="field-error" x-show="error" x-text="error" role="alert"></span>
            </div>
            <template x-if="sug">
                <div class="ai-box mt-3" aria-live="polite">
                    <x-callout x-show="sug.source === 'fallback'" class="small">The LLM service is unavailable right now, so these are the top TF-IDF terms of your title and abstract. Everything else keeps working.</x-callout>
                    <div><div class="eyebrow mb-1.5">Keywords</div>
                        <div class="chips">
                            <template x-for="k in sug.kw" :key="k.v">
                                <span class="sug" :class="k.s === 'editing' ? 'edited' : k.s">
                                    <template x-if="k.s === 'editing'">
                                        <span class="flex gap-1 items-center"><label class="sr-only" :for="'ed-' + k.v">Edit keyword</label>
                                            <input :id="'ed-' + k.v" x-model="k.e" class="min-h-8 rounded border border-line bg-surface px-2 text-[13px] w-40" @keydown.enter.prevent="act('kw', k, 'save')">
                                            <button type="button" class="btn btn-sm" @click="act('kw', k, 'save')">Save</button></span>
                                    </template>
                                    <template x-if="k.s !== 'editing'">
                                        <span class="flex gap-1 items-center">
                                            <span x-text="k.s === 'edited' ? k.e : k.v"></span>
                                            <template x-if="k.s === 'pending'"><span class="flex gap-1">
                                                <button type="button" class="btn btn-sm btn-ok" @click="act('kw', k, 'accept')">Accept</button>
                                                <button type="button" class="btn btn-sm" @click="act('kw', k, 'edit')">Edit</button>
                                                <button type="button" class="btn btn-sm" @click="act('kw', k, 'reject')" :aria-label="'Remove ' + k.v">✕</button></span></template>
                                            <template x-if="k.s !== 'pending'"><span class="flex gap-1 items-center"><span class="text-muted small" x-text="'· ' + ({accepted: 'Accepted', edited: 'Edited', rejected: 'Removed'})[k.s]"></span>
                                                <button type="button" class="btn btn-sm" @click="act('kw', k, 'undo')">Undo</button></span></template>
                                        </span>
                                    </template>
                                </span>
                            </template>
                        </div></div>
                    <div x-show="sug.sdg.length"><div class="eyebrow mb-1.5">SDG tags</div>
                        <div class="chips">
                            <template x-for="s in sug.sdg" :key="s.v">
                                <span class="sug" :class="s.s"><span x-text="'SDG ' + s.v + ' · ' + s.name"></span>
                                    <template x-if="s.s === 'pending'"><span class="flex gap-1"><button type="button" class="btn btn-sm btn-ok" @click="act('sdg', s, 'accept')">Accept</button><button type="button" class="btn btn-sm" @click="act('sdg', s, 'reject')">✕</button></span></template>
                                    <template x-if="s.s !== 'pending'"><button type="button" class="btn btn-sm" @click="act('sdg', s, 'undo')">Undo</button></template>
                                </span>
                            </template>
                        </div></div>
                    <div x-show="sug.type"><div class="eyebrow mb-1.5">Research type</div>
                        <template x-if="sug.type"><span class="sug" :class="sug.type.s"><span x-text="sug.type.label"></span>
                            <template x-if="sug.type.s === 'pending'"><span class="flex gap-1"><button type="button" class="btn btn-sm btn-ok" @click="act('type', sug.type, 'accept')">Accept</button><button type="button" class="btn btn-sm" @click="act('type', sug.type, 'reject')">✕</button></span></template>
                            <template x-if="sug.type.s !== 'pending'"><button type="button" class="btn btn-sm" @click="act('type', sug.type, 'undo')">Undo</button></template>
                        </span></template></div>
                    <p class="small text-muted">Each choice is saved to the activity log when you submit. The rule of 3 to 5 keywords still applies.</p>
                </div>
            </template>
        </x-panel>

        <x-panel title="Keywords and SDGs">
            <div class="flex flex-col gap-1"><label for="sb-kw" class="font-medium">Keywords * (3 to 5, separated by commas)</label>
                <input id="sb-kw" name="keywords" x-model="kwText" placeholder="tf-idf, cosine similarity, title screening" class="{{ $in }} {{ $err('keywords') }}">
                <span class="small" :class="keywords().length < 3 || keywords().length > 5 ? 'text-bad' : 'text-muted'" x-text="keywords().length + ' of 3–5 keywords'"></span>
                <x-form-error name="keywords" /></div>
            <fieldset class="mt-3.5">
                <legend class="eyebrow mb-1.5">Sustainable Development Goals</legend>
                <div class="chips">@foreach ($sdgs as $s)<label class="chip cursor-pointer min-h-9"><input type="checkbox" name="sdgs[]" value="{{ $s->id }}" x-model="sdgSel"> SDG {{ $s->id }} · {{ $s->name }}</label>@endforeach</div>
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
