{{-- "System settings" (CLAUDE.md §7 M9). One form per group; every saved change is logged with old and new values. --}}
@php
    $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3';
    $v = $values;
    $err = fn (string $bag, string $field) => $errors->getBag($bag)->first($field);
    $old = fn (string $bag, string $field, $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
@endphp
<x-layouts.app title="System settings">
    <x-page-head eyebrow="M9 · Settings" title="System settings" lead="Changes apply at once. Every change is written to the activity log with the old and new values." />

    <div class="g2">
        <x-panel title="Similarity thresholds" id="thresholds">
            <form method="POST" action="{{ route('admin.settings.update', 'thresholds') }}" novalidate>
                @csrf
                <p class="small text-muted mb-3">Applies to new screenings. The review threshold must be lower than the duplicate threshold. Screening never rejects a study by itself.</p>
                <div class="flex flex-col gap-1" x-data="{ v: {{ $old('thresholds', 'duplicate', $v['thresholds']['duplicate']) }} }"><label for="th-dup" class="font-medium">Probable duplicate at or above <b class="mono" x-text="Number(v).toFixed(2)"></b></label>
                    <input id="th-dup" name="duplicate" type="range" min="0.6" max="0.95" step="0.01" x-model="v"></div>
                <div class="flex flex-col gap-1 mt-3" x-data="{ v: {{ $old('thresholds', 'review', $v['thresholds']['review']) }} }"><label for="th-sim" class="font-medium">Needs review at or above <b class="mono" x-text="Number(v).toFixed(2)"></b></label>
                    <input id="th-sim" name="review" type="range" min="0.3" max="0.8" step="0.01" x-model="v" aria-describedby="th-sim-err"></div>
                @if ($m = $err('thresholds', 'review') ?: $err('thresholds', 'duplicate'))<p id="th-sim-err" class="field-error mt-1" role="alert">{{ $m }}</p>@endif
                <div class="mt-3"><x-button type="submit" variant="primary">Save thresholds</x-button></div>
            </form>
        </x-panel>

        <x-panel title="Clustering schedule" id="schedule">
            <form method="POST" action="{{ route('admin.settings.update', 'schedule') }}" class="flex flex-col gap-3 mt-2" novalidate>
                @csrf
                <div class="flex flex-col gap-1"><label for="cs-when" class="font-medium">Re-run</label>
                    <select id="cs-when" name="every" class="{{ $in }}">@foreach (['semester' => 'Every semester', 'month' => 'Every month', 'manual' => 'Manual only'] as $k => $l)<option value="{{ $k }}" @selected($old('schedule', 'every', $v['schedule']['every']) === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="flex flex-col gap-1"><label for="cs-n" class="font-medium">Also re-run after this many new approved records (0 = off)</label>
                    <input id="cs-n" name="after_new_records" type="number" min="0" class="{{ $in }}" value="{{ $old('schedule', 'after_new_records', $v['schedule']['after_new_records']) }}">
                    @if ($m = $err('schedule', 'after_new_records'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                <p class="small text-muted">A scheduled check runs every night at 2:30 AM and queues a run when one is due. A new run still needs the URDC's confirmation before it is used.</p>
                <div><x-button type="submit" variant="primary">Save schedule</x-button></div>
            </form>
        </x-panel>

        <x-panel title="LLM suggestions" id="llm">
            <x-slot:actions><span class="ai-tag">LLM</span></x-slot:actions>
            <form method="POST" action="{{ route('admin.settings.update', 'llm') }}" class="flex flex-col gap-2.5 mt-2" novalidate>
                @csrf
                <input type="hidden" name="enabled" value="0">
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" name="enabled" value="1" class="size-5" @checked($v['llm']['enabled'])> Use the LLM for metadata and theme-label suggestions</label>
                <div class="form-grid">
                    <div class="flex flex-col gap-1"><label for="llm-to" class="font-medium">Timeout before fallback (seconds)</label><input id="llm-to" name="timeout" type="number" class="{{ $in }}" value="{{ $old('llm', 'timeout', $v['llm']['timeout']) }}">
                        @if ($m = $err('llm', 'timeout'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                    <div class="flex flex-col gap-1"><label for="llm-rl" class="font-medium">Requests per minute</label><input id="llm-rl" name="rate_per_minute" type="number" class="{{ $in }}" value="{{ $old('llm', 'rate_per_minute', $v['llm']['rate_per_minute']) }}">
                        @if ($m = $err('llm', 'rate_per_minute'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                </div>
                <div class="flex flex-col gap-1"><label for="llm-fields" class="font-medium">Fields sent (read-only)</label><input id="llm-fields" class="{{ $in }}" value="{{ implode(', ', str_replace('_', ' ', $fields)) }}" disabled></div>
                <p class="small text-muted">Fallback when off or unavailable: top TF-IDF terms. The provider and key are set in the server's <span class="mono">.env</span> file, not here. @if (! config('tua.llm.enabled') || ! filled(config('tua.llm.api_key')))They are not set now, so the fallback is always used. @endif</p>
                <div><x-button type="submit" variant="primary">Save LLM settings</x-button></div>
            </form>
        </x-panel>

        <x-panel title="Files and abstracts" id="files">
            <form method="POST" action="{{ route('admin.settings.update', 'files') }}" novalidate>
                @csrf
                <div class="form-grid mt-2">
                    <div class="flex flex-col gap-1"><label for="fl-max" class="font-medium">Max file size (MB)</label><input id="fl-max" name="max_mb" type="number" class="{{ $in }}" value="{{ $old('files', 'max_mb', $v['files']['max_mb']) }}">
                        @if ($m = $err('files', 'max_mb'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                    <div class="flex flex-col gap-1"><label for="fl-words" class="font-medium">Abstract word limit</label><input id="fl-words" name="abstract_word_limit" type="number" class="{{ $in }}" value="{{ $old('files', 'abstract_word_limit', $v['files']['abstract_word_limit']) }}">
                        @if ($m = $err('files', 'abstract_word_limit'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                    <div class="full flex flex-col gap-1"><label for="fl-types" class="font-medium">Allowed types (per slot, read-only)</label><input id="fl-types" class="{{ $in }}" value="Word (.doc, .docx) for abstract and references · PDF for the others" disabled></div>
                </div>
                <div class="mt-3"><x-button type="submit" variant="primary">Save file settings</x-button></div>
            </form>
        </x-panel>

        <x-panel title="Sessions and sign-in" id="signin">
            <form method="POST" action="{{ route('admin.settings.update', 'signin') }}" novalidate>
                @csrf
                <div class="form-grid mt-2">
                    <div class="flex flex-col gap-1"><label for="ss-to" class="font-medium">Session timeout when idle (minutes)</label><input id="ss-to" name="idle_minutes" type="number" class="{{ $in }}" value="{{ $old('signin', 'idle_minutes', $v['signin']['idle_minutes']) }}">
                        @if ($m = $err('signin', 'idle_minutes'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                    <div class="flex flex-col gap-1"><label for="ss-rl" class="font-medium">Failed sign-ins before a 1-minute pause</label><input id="ss-rl" name="login_max_attempts" type="number" class="{{ $in }}" value="{{ $old('signin', 'login_max_attempts', $v['signin']['login_max_attempts']) }}">
                        @if ($m = $err('signin', 'login_max_attempts'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                    <div class="full flex flex-col gap-1"><label for="ss-dom" class="font-medium">Allowed email domain</label><input id="ss-dom" class="{{ $in }}" value="{{ config('tua.email_domain') }}" disabled></div>
                </div>
                <div class="mt-3"><x-button type="submit" variant="primary">Save sign-in settings</x-button></div>
            </form>
        </x-panel>

        <x-panel title="Recovery channels" id="recovery">
            <x-slot:actions><x-pill variant="gold">Not in manuscript</x-pill></x-slot:actions>
            <form method="POST" action="{{ route('admin.settings.update', 'recovery') }}" class="flex flex-col gap-1 mt-2" novalidate>
                @csrf
                <input type="hidden" name="recovery_email" value="0"><input type="hidden" name="sms" value="0">
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" class="size-5" checked disabled> TUA email (always on)</label>
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" name="recovery_email" value="1" class="size-5" @checked($v['recovery']['recovery_email'] ?? false) @disabled(! $recoveryBuilt['recovery_email'])> Recovery email</label>
                <label class="flex items-center gap-2 min-h-11"><input type="checkbox" name="sms" value="1" class="size-5" @checked($v['recovery']['sms'] ?? false) @disabled(! $recoveryBuilt['sms'])> Text message (SMS gateway)</label>
                @if (! $recoveryBuilt['sms'])<p class="small text-muted">SMS is switched off in the server configuration (<span class="mono">RECOVERY_SMS_ENABLED=false</span>).</p>@endif
                <div class="mt-2"><x-button type="submit" variant="primary">Save recovery channels</x-button></div>
            </form>
        </x-panel>

        <x-panel title="Activity log retention" id="retention">
            <form method="POST" action="{{ route('admin.settings.update', 'retention') }}" class="flex flex-col gap-3 mt-2" novalidate>
                @csrf
                <div class="flex flex-col gap-1"><label for="rt-years" class="font-medium">Keep activity log entries for (years)</label>
                    <input id="rt-years" name="years" type="number" min="1" max="10" class="{{ $in }}" value="{{ $old('retention', 'years', $v['retention']['years']) }}" aria-describedby="rt-help">
                    @if ($m = $err('retention', 'years'))<p class="field-error" role="alert">{{ $m }}</p>@endif</div>
                <p id="rt-help" class="small text-muted">From 1 to 10 years. Every night at 3:00 AM, entries older than this are removed, and the removal itself is logged. No one can edit or delete a single entry. Removed entries stay in the backups until those expire (3 months at most).</p>
                <div><x-button type="submit" variant="primary">Save retention</x-button></div>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
