<x-layouts.app title="Evaluator accounts">
    <x-page-head eyebrow="M1 · M9 · Evaluation copy" title="Evaluator accounts"
        lead="Temporary accounts for external IT experts on the evaluation site. They see sample and anonymized records only, and expire automatically." />
    <x-preview-note sprint="10" what="These sample accounts and Create account" />
    @unless ($isEvalCopy)
        <x-callout variant="gold" class="mb-4">This is not the evaluation copy (<span class="mono">APP_EVALUATION_COPY=false</span>). Evaluator accounts can only be created on the evaluation site.</x-callout>
    @endunless
    <div class="split">
        <div class="tbl">
            <table>
                <thead><tr><th>Account</th><th>Evaluator</th><th>Created</th><th>Expires</th><th>Status</th></tr></thead>
                <tbody>@foreach ($evaluators as $e)<tr><td class="mono small">{{ $e[0] }}</td><td>{{ $e[1] }}</td><td class="small">{{ $e[2] }}</td><td class="small">{{ $e[3] }}</td><td><x-pill :variant="$e[4] === 'Active' ? 'ok' : null">{{ $e[4] }}</x-pill></td></tr>@endforeach</tbody>
            </table>
        </div>
        <x-panel title="New evaluator">
            <div class="flex flex-col gap-2.5 mt-2">
                <x-input name="ev_name" label="Name or label" value="IT expert 05" />
                <div class="flex flex-col gap-1"><label for="ev-exp" class="font-medium">Expires on</label>
                    <select id="ev-exp" class="min-h-11 rounded-md border border-line bg-surface px-3"><option>Dec 15, 2026</option><option>Dec 31, 2026</option><option>Jan 15, 2027</option></select></div>
                <x-button variant="primary" preview="Sprint 10">Create account</x-button>
                <p class="small text-muted">Every evaluator action is logged. Accounts cannot exist in production.</p>
            </div>
        </x-panel>
    </div>
</x-layouts.app>
