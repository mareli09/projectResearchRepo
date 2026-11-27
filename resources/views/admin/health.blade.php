<x-layouts.app title="System health">
    <x-page-head eyebrow="M9 · System health" title="System health">
        <x-slot:actions><x-pill :variant="$failed || $mailFailed ? 'warn' : 'ok'">{{ $failed || $mailFailed ? 'Needs attention' : 'All services running' }}</x-pill></x-slot:actions>
    </x-page-head>
    <x-preview-note sprint="9" what="Figures marked sample, and Retry failed jobs" />

    <div class="g5 mb-4">
        <x-kpi label="Web app" value="Online" tone="ok" />
        <x-kpi label="Analytics service" value="Local only" hint="checked on Services and security" />
        <x-kpi label="LLM service" :value="config('tua.llm.enabled') ? 'Reachable' : 'Turned off'" :tone="config('tua.llm.enabled') ? 'ok' : 'warn'" />
        <x-kpi label="Queue" :value="number_format($queue)" hint="jobs waiting (live)" />
        <x-kpi label="Failed jobs" :value="number_format($failed)" :tone="$failed ? 'bad' : 'ok'" :hint="$mailFailed.' failed emails (live)'" />
    </div>

    <div class="g2">
        <x-panel title="Server">
            <x-slot:actions><span class="text-muted small">ICT Office · Ubuntu · Intel · tua.edu.ph subdomain with SSL</span></x-slot:actions>
            @php
                $rows = [
                    ['Disk used of 70 GB (sample)', 38, 70],
                    ['Uploaded files (sample)', 24, 70],
                    ['Database (sample)', 4, 70],
                    ['Memory in use of 4 GB (sample)', 2.6, 4],
                ];
                if ($diskUsedGb !== null) {
                    array_unshift($rows, ['Disk used on this machine (live)', $diskUsedGb, $diskTotalGb]);
                }
            @endphp
            @foreach ($rows as [$l, $v, $max])
                <div class="my-2.5"><div class="ph small"><span>{{ $l }}</span><span class="num">{{ $v }} GB</span></div>
                    <div class="meter" role="progressbar" aria-valuenow="{{ $v }}" aria-valuemax="{{ $max }}" aria-label="{{ $l }}"><i style="width: {{ min(100, $v / max(0.1, $max) * 100) }}%; {{ $v / max(0.1, $max) >= .8 ? 'background: var(--bad)' : '' }}"></i></div></div>
            @endforeach
            <p class="small text-muted">An alert fires at 80% disk use. Clustering runs as a scheduled job so it fits in memory. The LLM runs on the external service.</p>
        </x-panel>
        <x-panel title="Background jobs">
            <x-slot:actions><span class="text-muted small">sample</span></x-slot:actions>
            <div class="tbl"><table>
                <thead><tr><th>Job</th><th>Status</th><th>When</th></tr></thead>
                <tbody>@foreach ($jobs as $j)<tr><td class="mono small">{{ $j[0] }}</td><td><x-pill :variant="$j[1]">{{ $j[2] }}</x-pill></td><td class="small">{{ $j[3] }}</td></tr>@endforeach</tbody>
            </table></div>
            <div class="mt-2.5"><x-button size="sm" preview="Sprint 9">Retry failed jobs</x-button></div>
        </x-panel>
    </div>
</x-layouts.app>
