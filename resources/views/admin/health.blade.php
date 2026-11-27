{{-- "System health" (CLAUDE.md §7 M9). Every figure is live. --}}
@php
    $attention = $failed || $mail['failed'] || ! $analytics['ok'];
    $rows = array_filter([
        $disk['used'] !== null ? ['Disk used'.($disk['total'] ? ' of '.$disk['total'].' GB on this machine' : ''), $disk['used'], $disk['total'] ?: \App\Domain\Admin\SystemHealth::SERVER_DISK_GB] : null,
        ['Uploaded research files', $filesGb, \App\Domain\Admin\SystemHealth::SERVER_DISK_GB],
        $dbGb !== null ? ['Database', $dbGb, \App\Domain\Admin\SystemHealth::SERVER_DISK_GB] : null,
        $memory['used'] !== null ? ['Memory in use of '.$memory['total'].' GB', $memory['used'], $memory['total']] : null,
    ]);
@endphp
<x-layouts.app title="System health">
    <x-page-head eyebrow="M9 · System health" title="System health">
        <x-slot:actions><x-pill :variant="$attention ? 'warn' : 'ok'">{{ $attention ? 'Needs attention' : 'All services running' }}</x-pill></x-slot:actions>
    </x-page-head>

    <div class="g5 mb-4">
        <x-kpi label="Web app" value="Online" tone="ok" />
        <x-kpi label="Analytics service" :value="$analytics['ok'] ? 'Running' : 'Not reachable'" :tone="$analytics['ok'] ? 'ok' : 'bad'" :hint="$analytics['ok'] ? $analytics['ms'].' ms' : 'start it with composer analytics'" />
        <x-kpi label="LLM service" :value="$llm ? 'Turned on' : 'Turned off'" :tone="$llm ? 'ok' : 'warn'" :hint="$llm ? 'test it on Services and security' : 'top-terms fallback in use'" />
        <x-kpi label="Queue" :value="number_format($queue)" hint="jobs waiting" />
        <x-kpi label="Failed jobs" :value="number_format($failed)" :tone="$failed ? 'bad' : 'ok'" :hint="$mail['failed'].' failed emails'" />
    </div>

    <div class="g2">
        <x-panel title="Server">
            <x-slot:actions><span class="text-muted small">Target: ICT Office server · Ubuntu · Intel · {{ \App\Domain\Admin\SystemHealth::SERVER_RAM_GB }} GB RAM · {{ \App\Domain\Admin\SystemHealth::SERVER_DISK_GB }} GB</span></x-slot:actions>
            @foreach ($rows as [$l, $v, $max])
                @php $pct = $v / max(0.001, $max); @endphp
                <div class="my-2.5"><div class="ph small"><span>{{ $l }}</span><span class="num">{{ $v }} GB</span></div>
                    <div class="meter" role="progressbar" aria-valuenow="{{ $v }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-label="{{ $l }}"><i style="width: {{ min(100, $pct * 100) }}%; {{ $pct >= .8 ? 'background: var(--bad)' : '' }}"></i></div></div>
            @endforeach
            @if ($memory['used'] === null)<p class="small text-muted">Memory use is read from the Ubuntu server and is not available on this machine.</p>@endif
            <p class="small text-muted">An alert fires at 80% disk use. Clustering runs as a scheduled job so it fits in memory. The LLM runs on the external service.</p>
        </x-panel>

        <x-panel title="Background jobs">
            <x-slot:actions><span class="text-muted small">waiting and failed</span></x-slot:actions>
            <div class="tbl"><table>
                <thead><tr><th>Job</th><th>Status</th><th>When</th></tr></thead>
                <tbody>
                    @forelse ($jobs as $j)<tr><td class="mono small">{{ $j['name'] }}</td><td><x-pill :variant="$j['tone']">{{ $j['status'] }}</x-pill></td><td class="small whitespace-nowrap">{{ $j['when'] }}</td></tr>
                    @empty<tr><td colspan="3" class="text-muted">No jobs waiting and none failed.</td></tr>@endforelse
                </tbody>
            </table></div>
            <form method="POST" action="{{ route('admin.jobs.retry') }}" class="mt-2.5">@csrf<x-button type="submit" size="sm" :disabled="! $failed">Retry failed jobs</x-button></form>
            <p class="small text-muted mt-2">Jobs run only while the queue worker is running (Supervisor on the server, <span class="mono">php artisan queue:work</span> locally).</p>
        </x-panel>

        @if ($mail['recent'] !== [])
            <x-panel title="Failed emails" class="span2">
                <div class="tbl"><table>
                    <thead><tr><th>To</th><th>Subject</th><th>Error</th><th>When</th></tr></thead>
                    <tbody>@foreach ($mail['recent'] as $m)<tr><td class="small">{{ $m['to'] }}</td><td class="small">{{ $m['subject'] }}</td><td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $m['error'], 90) }}</td><td class="small whitespace-nowrap">{{ $m['when'] }}</td></tr>@endforeach</tbody>
                </table></div>
                <p class="small text-muted mt-2">Each email is tried 3 times. Failed email jobs can be retried with "Retry failed jobs".</p>
            </x-panel>
        @endif
    </div>
</x-layouts.app>
