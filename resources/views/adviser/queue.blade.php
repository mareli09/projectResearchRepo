<x-layouts.app title="Endorsement queue">
    <x-page-head eyebrow="Faculty adviser · M3" title="Endorsement queue" lead="Submissions of your advisees. Endorse them, or return them with a reason." />
    <div class="g3 mb-4">@foreach ($kpis as [$l, $v, $icon, $tone])<x-kpi :label="$l" :value="$v" :icon="$icon" :icon-tone="$tone" />@endforeach</div>
    @if ($mine->isEmpty())
        <x-empty-state title="No submissions yet">When your advisees submit, their studies appear here and you get an email.</x-empty-state>
    @else
        <div class="tbl">
            <table>
                <thead><tr><th>ID</th><th>Title</th><th>Student</th><th>Submitted</th><th style="min-width:150px">Similarity</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($mine as $s)
                        <tr>
                            <td class="mono small">{{ $s['id'] }}</td>
                            <td><a href="{{ route('adviser.review', ['id' => $s['id']]) }}">{{ $s['title'] }}</a></td>
                            <td>{{ $s['student'] }}</td>
                            <td class="small">{{ $s['history'][0][2] ?? '—' }}</td>
                            <td>@if ($s['screened'])<x-score-bar :score="$s['score']" />@else<span class="small text-muted">Queued</span>@endif</td>
                            <td><x-status-pill :status="$s['status']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
