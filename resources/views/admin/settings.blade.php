{{-- "System settings" (CLAUDE.md §7 M9). Values are read from the settings table; saving is built in Sprint 9 (every change logged). --}}
@php $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3'; @endphp
<x-layouts.app title="System settings">
    <x-page-head eyebrow="M9 · Settings" title="System settings" />
    <x-preview-note sprint="9" what="The values shown are the live settings, but the Save buttons" />

    <div class="g2">
        <x-panel title="Similarity thresholds">
            <p class="small text-muted mb-3">Applies to new screenings. The review threshold must be lower than the duplicate threshold. Changes are logged.</p>
            <div class="flex flex-col gap-1" x-data="{ v: {{ $thresholds['duplicate'] }} }"><label for="th-dup" class="font-medium">Probable duplicate at or above <b class="mono" x-text="Number(v).toFixed(2)"></b></label>
                <input id="th-dup" type="range" min="0.6" max="0.95" step="0.01" x-model="v"></div>
            <div class="flex flex-col gap-1 mt-3" x-data="{ v: {{ $thresholds['review'] }} }"><label for="th-sim" class="font-medium">Needs review at or above <b class="mono" x-text="Number(v).toFixed(2)"></b></label>
                <input id="th-sim" type="range" min="0.3" max="0.8" step="0.01" x-model="v"></div>
            <div class="mt-3"><x-button variant="primary" preview="Sprint 9">Save thresholds</x-button></div>
        </x-panel>
        <x-panel title="Clustering schedule">
            <div class="flex flex-col gap-3 mt-2">
                <div class="flex flex-col gap-1"><label for="cs-when" class="font-medium">Re-run</label>
                    <select id="cs-when" class="{{ $in }}">@foreach (['semester' => 'Every semester', 'month' => 'Every month', 'manual' => 'Manual only'] as $k => $l)<option @selected($schedule['every'] === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="flex flex-col gap-1"><label for="cs-n" class="font-medium">Also re-run after this many new approved records</label><input id="cs-n" type="number" class="{{ $in }}" value="{{ $schedule['after_new_records'] }}"></div>
                <div><x-button variant="primary" preview="Sprint 9">Save schedule</x-button></div>
            </div>
        </x-panel>
        <x-panel title="LLM suggestions">
            <x-slot:actions><span class="ai-tag">LLM</span></x-slot:actions>
            <div class="flex flex-col gap-2.5 mt-2">
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" @checked($llm['enabled'])> Offer keyword, SDG and research-type suggestions at submission</label>
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" @checked($llm['enabled'])> Suggest theme labels after each clustering run</label>
                <div class="form-grid">
                    <div class="flex flex-col gap-1"><label for="llm-to" class="font-medium">Timeout before fallback (seconds)</label><input id="llm-to" type="number" class="{{ $in }}" value="{{ $llm['timeout'] }}"></div>
                    <div class="flex flex-col gap-1"><label for="llm-rl" class="font-medium">Requests per minute</label><input id="llm-rl" type="number" class="{{ $in }}" value="{{ $llm['rate'] }}"></div>
                </div>
                <div class="flex flex-col gap-1"><label for="llm-fields" class="font-medium">Fields sent (read-only)</label><input id="llm-fields" class="{{ $in }}" value="{{ implode(', ', str_replace('_', ' ', $fields)) }}" disabled></div>
                <p class="small text-muted">Fallback when unavailable: top TF-IDF terms. The service key is set in the server configuration file, not here.</p>
                <div><x-button variant="primary" preview="Sprint 9">Save LLM settings</x-button></div>
            </div>
        </x-panel>
        <x-panel title="Files and abstracts">
            <div class="form-grid mt-2">
                <div class="flex flex-col gap-1"><label for="fl-max" class="font-medium">Max file size (MB)</label><input id="fl-max" type="number" class="{{ $in }}" value="{{ $maxMb }}"></div>
                <div class="flex flex-col gap-1"><label for="fl-words" class="font-medium">Abstract word limit</label><input id="fl-words" type="number" class="{{ $in }}" value="{{ $wordLimit }}"></div>
                <div class="full flex flex-col gap-1"><label for="fl-types" class="font-medium">Allowed types (per slot, read-only)</label><input id="fl-types" class="{{ $in }}" value="Word (.doc, .docx) for abstract and references · PDF for the others" disabled></div>
            </div>
            <div class="mt-3"><x-button variant="primary" preview="Sprint 9">Save file settings</x-button></div>
        </x-panel>
        <x-panel title="Sessions and sign-in">
            <div class="form-grid mt-2">
                <div class="flex flex-col gap-1"><label for="ss-to" class="font-medium">Session timeout (minutes)</label><input id="ss-to" type="number" class="{{ $in }}" value="{{ $session }}"></div>
                <div class="flex flex-col gap-1"><label for="ss-rl" class="font-medium">Sign-in attempts before pause</label><input id="ss-rl" type="number" class="{{ $in }}" value="{{ $attempts }}"></div>
                <div class="full flex flex-col gap-1"><label for="ss-dom" class="font-medium">Allowed email domain</label><input id="ss-dom" class="{{ $in }}" value="{{ config('tua.email_domain') }}" disabled></div>
            </div>
            <div class="mt-3"><x-button variant="primary" preview="Sprint 9">Save sign-in settings</x-button></div>
        </x-panel>
        <x-panel title="Recovery channels">
            <x-slot:actions><x-pill variant="gold">Not in manuscript</x-pill></x-slot:actions>
            <div class="flex flex-col gap-1 mt-2">
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" checked disabled> TUA email (always on)</label>
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" @checked($recovery['recovery_email'] ?? false)> Recovery email</label>
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" @checked($recovery['sms'] ?? false)> Text message (SMS gateway)</label>
            </div>
            <div class="mt-3"><x-button variant="primary" preview="Sprint 9">Save recovery channels</x-button></div>
        </x-panel>
    </div>
</x-layouts.app>
