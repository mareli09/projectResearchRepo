<x-layouts.app title="Theme clustering">
    <x-page-head eyebrow="M6 · Thematic clustering · K-Means (k-means++) on TF-IDF" title="Theme clustering"
        :lead="'K-Means on the TF-IDF vectors of the '.number_format($records).' published studies, k = '.config('tua.clustering.k_min').'–'.config('tua.clustering.k_max').'. The k with the best silhouette is suggested. A run becomes current only after you confirm it.'">
        <x-slot:actions>
            <form method="POST" action="{{ route('urdc.clustering.run') }}" class="flex items-end gap-2">
                @csrf
                <div class="flex flex-col gap-1"><label for="cl-k" class="text-[13px] font-medium">k (optional)</label>
                    <select id="cl-k" name="k" class="min-h-11 rounded-md border border-line bg-surface px-3"><option value="">Best silhouette</option>@foreach (range(2, 15) as $k)<option>{{ $k }}</option>@endforeach</select></div>
                <x-button type="submit" variant="primary" :disabled="$busy">{{ $busy ? 'Run in progress…' : 'Run clustering now' }}</x-button>
            </form>
        </x-slot:actions>
    </x-page-head>
    <x-form-error name="run" class="mb-3" />

    @if ($busy)
        <x-callout class="mb-4" role="status"><b>{{ $latest->public_id }}</b> is {{ $latest->state }}. It runs as a background job (the queue worker must be running) and takes about a minute for 1,500 studies. Refresh this page to see the result.</x-callout>
    @elseif ($latest?->state === 'failed')
        <x-callout variant="bad" class="mb-4"><b>{{ $latest->public_id }} failed.</b> Check that the analytics service is running (<span class="mono">composer analytics</span>), then run again. The error is in the activity log.</x-callout>
    @endif

    @if ($run)
        @if ($run->state === 'done')
            <x-callout variant="gold" class="mb-4">
                <form method="POST" action="{{ route('urdc.clustering.confirm', $run) }}" class="flex flex-wrap items-center justify-between gap-3">
                    @csrf
                    <span><b>{{ $run->public_id }}</b> finished with k = {{ $run->k }}. Review the themes below, then make it the current set used by search and dashboards.</span>
                    <x-button type="submit" variant="ok">Confirm as current</x-button>
                </form>
            </x-callout>
        @endif

        <div class="g5 mb-4">
            <x-kpi label="Run" :value="$run->public_id" :hint="$run->state === 'current' ? 'current' : 'waiting for confirmation'" icon="cluster" />
            <x-kpi label="Records clustered" :value="number_format($run->n_records)" icon="book" icon-tone="ok" />
            <x-kpi label="Chosen k" :value="$run->k" icon="tag" icon-tone="gold" />
            <x-kpi label="Silhouette" :value="$run->silhouette !== null ? number_format($run->silhouette, 3) : '—'" hint="higher is better" icon="chart" icon-tone="ok" />
            <x-kpi label="Davies–Bouldin" :value="$run->davies_bouldin !== null ? number_format($run->davies_bouldin, 2) : '—'" hint="lower is better" icon="chart" icon-tone="warn" />
        </div>

        <div class="g2 mb-4">
            <x-panel title="Elbow method"><x-slot:actions><span class="text-muted small">inertia for each k</span></x-slot:actions>
                @if ($elbow)<x-chart :option="$elbow" label="Elbow chart" />@else<p class="text-muted">Scores appear after a run made in this system.</p>@endif</x-panel>
            <x-panel title="Average silhouette"><x-slot:actions><span class="text-muted small">highest at k = {{ $run->k }}</span></x-slot:actions>
                @if ($silhouette)<x-chart :option="$silhouette" label="Silhouette by k" />@else<p class="text-muted">Scores appear after a run made in this system.</p>@endif</x-panel>
        </div>

        <x-panel :title="'Themes in '.$run->public_id" class="mb-4">
            <x-slot:actions><span class="text-muted small"><span class="ai-tag">LLM</span> suggests labels · coordinators and the URDC confirm · <a href="{{ route('themes.review') }}">review labels</a></span></x-slot:actions>
            <div class="tbl"><table>
                <thead><tr><th>Cluster</th><th>Top terms (centroid weights)</th><th>Suggested label</th><th>Label in use</th><th class="r">Studies</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($clusters as $c)
                        @php $s = $suggestions[$c->id] ?? null; @endphp
                        <tr><td class="mono">C{{ $c->number }}</td>
                            <td class="small mono">{{ implode(' · ', array_slice($c->top_terms, 0, 6)) }}</td>
                            <td class="small">{{ $s?->suggested ?? '—' }} @if ($s?->fallback_used)<x-pill>top terms</x-pill>@endif</td>
                            <td class="small font-medium">{{ $c->label }}</td>
                            <td class="r num">{{ number_format($c->size) }}</td>
                            <td>@if ($c->label_status === 'confirmed')<x-pill variant="ok">{{ ucfirst($s?->action ?? 'confirmed') }}</x-pill>@else<x-pill variant="warn">Waiting</x-pill>@endif</td></tr>
                    @endforeach
                </tbody>
            </table></div>
            <p class="small text-muted mt-2">If the LLM service is unavailable, the top three terms are proposed as the label. The LLM never sees full papers or personal data.</p>
        </x-panel>
    @else
        <x-empty-state title="No clustering runs yet">Start the analytics service and run clustering to find the themes of the repository.</x-empty-state>
    @endif

    <x-panel title="Run history" class="mt-4">
        <div class="tbl"><table>
            <thead><tr><th>Run</th><th>Finished</th><th>Started by</th><th class="r">Records</th><th class="r">k</th><th class="r">Silhouette</th><th class="r">Davies–Bouldin</th><th>State</th></tr></thead>
            <tbody>
                @forelse ($runs as $r)
                    <tr><td class="mono">{{ $r->public_id }}</td><td class="small">{{ $r->finished_at?->format('M j, Y · g:i A') ?? '—' }}</td><td class="small">{{ $r->trigger_label }}</td>
                        <td class="r num">{{ number_format($r->n_records) }}</td><td class="r num">{{ $r->k ?: '—' }}</td>
                        <td class="r mono">{{ $r->silhouette !== null ? number_format($r->silhouette, 3) : '—' }}</td><td class="r mono">{{ $r->davies_bouldin !== null ? number_format($r->davies_bouldin, 2) : '—' }}</td>
                        <td><x-pill :variant="['current' => 'ok', 'done' => 'gold', 'failed' => 'bad', 'running' => 'info', 'queued' => 'info'][$r->state] ?? null">{{ ucfirst($r->state) }}</x-pill></td></tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No runs yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="small text-muted mt-2">Earlier runs are kept for comparison. Schedule: each semester or after {{ config('tua.clustering.refit_after_records') }} new records (Settings).</p>
    </x-panel>
</x-layouts.app>
