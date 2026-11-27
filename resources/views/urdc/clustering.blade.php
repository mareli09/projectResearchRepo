<x-layouts.app title="Theme clustering">
    <x-page-head eyebrow="M6 · Thematic clustering · K-Means (k-means++) on TF-IDF" title="Theme clustering">
        <x-slot:actions><x-button variant="primary" preview="Sprint 6">Run clustering now</x-button></x-slot:actions>
    </x-page-head>
    <x-preview-note sprint="6" what="The runs and scores are sample, and Run clustering now" />

    @php $cur = $runs[0]; @endphp
    <div class="g5 mb-4">
        <x-kpi label="Current run" :value="$cur['id']" /><x-kpi label="Records clustered" :value="number_format($cur['n'])" />
        <x-kpi label="Chosen k" :value="$cur['k']" /><x-kpi label="Silhouette" :value="number_format($cur['sil'], 3)" /><x-kpi label="Davies–Bouldin" :value="number_format($cur['db'], 2)" />
    </div>
    <div class="g2 mb-4">
        <x-panel title="Elbow method"><x-slot:actions><span class="text-muted small">inertia for k = 2–15</span></x-slot:actions><x-chart :option="$elbow" label="Elbow chart" /></x-panel>
        <x-panel title="Average silhouette"><x-slot:actions><span class="text-muted small">highest at k = 7</span></x-slot:actions><x-chart :option="$silhouette" label="Silhouette by k" /></x-panel>
    </div>
    <x-panel :title="'Themes in '.$cur['id']" class="mb-4">
        <x-slot:actions><span class="text-muted small"><span class="ai-tag">LLM</span> suggests labels · coordinators and the URDC confirm</span></x-slot:actions>
        <div class="tbl"><table>
            <thead><tr><th>Cluster</th><th>Top terms</th><th>LLM suggestion</th><th>Label in use</th><th class="r">Studies</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($labels as $l)
                    <tr><td class="mono">C{{ $l['id'] }}</td><td class="small mono">{{ implode(' · ', array_slice($l['terms'], 0, 5)) }}</td><td class="small">{{ $l['llm'] }}</td>
                        <td><label class="sr-only" for="lbl-{{ $l['id'] }}">Label C{{ $l['id'] }}</label><input id="lbl-{{ $l['id'] }}" value="{{ $l['label'] }}" class="w-full min-h-9 rounded-md border border-line bg-surface px-2 text-[13px]"></td>
                        <td class="r num">{{ number_format($counts[$l['id']] ?? 0) }}</td>
                        <td>@if ($l['confirmed'])<x-pill variant="ok">{{ ucfirst($l['action']) }}</x-pill>@else<x-pill variant="warn">Waiting</x-pill>@endif</td></tr>
                @endforeach
            </tbody>
        </table></div>
        <p class="small text-muted mt-2">If the LLM service is unavailable, the top three terms are proposed as the label. The LLM never sees full papers or personal data.</p>
    </x-panel>
    <x-panel title="Run history">
        <div class="tbl"><table>
            <thead><tr><th>Run</th><th>Date</th><th>Started by</th><th class="r">Records</th><th class="r">k</th><th class="r">Silhouette</th><th class="r">Davies–Bouldin</th><th></th></tr></thead>
            <tbody>
                @foreach ($runs as $r)
                    <tr><td class="mono">{{ $r['id'] }}</td><td>{{ $r['date'] }}</td><td class="small">{{ $r['by'] }}</td><td class="r num">{{ number_format($r['n']) }}</td><td class="r num">{{ $r['k'] }}</td>
                        <td class="r mono">{{ number_format($r['sil'], 3) }}</td><td class="r mono">{{ number_format($r['db'], 2) }}</td>
                        <td>@if ($r['state'] === 'Current')<x-pill variant="ok">Current</x-pill>@else<x-pill>Kept</x-pill>@endif</td></tr>
                @endforeach
            </tbody>
        </table></div>
        <p class="small text-muted mt-2">Runs each semester, or after {{ config('tua.clustering.refit_after_records') }} new approved records. A run becomes current only after the URDC confirms it; earlier runs are kept.</p>
    </x-panel>
</x-layouts.app>
