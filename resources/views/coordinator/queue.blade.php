<x-layouts.app title="Review queue">
    <x-page-head :eyebrow="$cluster ? 'Research coordinator · Cluster '.$cluster->number.' · '.$cluster->name : 'Research coordinator'" title="Review queue" />
    @unless ($cluster)
        <x-callout variant="gold" class="mb-4">You are not assigned to a research cluster yet. The System administrator assigns coordinators to clusters.</x-callout>
    @endunless
    <div class="g3 mb-4">@foreach ($kpis as [$l, $v, $icon, $tone])<x-kpi :label="$l" :value="$v" :icon="$icon" :icon-tone="$tone" />@endforeach</div>
    <div class="tbl mb-4">
        <table>
            <thead><tr><th>ID</th><th>Title</th><th>Adviser</th><th style="min-width:150px">Highest match</th><th>Flag</th><th></th></tr></thead>
            <tbody>
                @forelse ($queue as $s)
                    <tr>
                        <td class="mono small">{{ $s['id'] }}</td>
                        <td>{{ $s['title'] }}<div class="text-muted small">{{ $s['student'] }} · {{ $s['program'] }}</div></td>
                        <td class="small">{{ $s['adviser'] }}</td>
                        <td>@if ($s['screened'])<x-score-bar :score="$s['score']" />@else<span class="small text-muted">Queued</span>@endif</td>
                        <td>@if ($s['screened'])<x-flag-pill :score="$s['score']" />@endif</td>
                        <td class="r"><x-button size="sm" variant="primary" :href="route('coordinator.screening', ['id' => $s['id']])">Review</x-button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Nothing waiting. Endorsed submissions of your cluster appear here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-panel title="Recent decisions">
        <div class="list">
            @forelse ($recent as $s)
                <div><div class="ph"><a href="{{ route('coordinator.screening', ['id' => $s['id']]) }}">{{ $s['title'] }}</a><x-status-pill :status="$s['status']" /></div>
                    <div class="small text-muted">{{ collect($s['history'])->firstWhere(0, 'Reviewed')[3] ?? '' }}</div></div>
            @empty
                <p class="text-muted">No decisions yet.</p>
            @endforelse
        </div>
    </x-panel>
</x-layouts.app>
