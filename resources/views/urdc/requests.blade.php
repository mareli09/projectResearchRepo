<x-layouts.app title="Full-paper requests">
    <x-page-head eyebrow="M4 · Access to full papers" title="Full-paper requests"
        lead="Abstracts are open to everyone signed in. Students get a full paper when the URDC approves a request, following University policy. Approved downloads are permission-checked and logged." />
    <x-preview-note sprint="5" what="These sample requests and Approve and Decline" />
    <div class="tbl">
        <table>
            <thead><tr><th>Request</th><th>Requested by</th><th>Study</th><th>Purpose</th><th>Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($requests as $a)
                    <tr>
                        <td class="mono small">{{ $a['id'] }}</td>
                        <td>{{ $a['who'] }}<div class="text-muted small">{{ $a['role'] }}</div></td>
                        <td class="small"><a href="{{ route('studies.show', $a['rec']) }}">{{ $a['rec'] }}</a><div class="text-muted">{{ \Illuminate\Support\Str::limit($titles[$a['rec']] ?? '', 60) }}</div></td>
                        <td class="small">{{ $a['purpose'] }}</td>
                        <td class="small">{{ $a['date'] }}</td>
                        <td><x-pill :variant="['Approved' => 'ok', 'Declined' => 'bad'][$a['status']] ?? 'warn'">{{ $a['status'] }}</x-pill></td>
                        <td class="r whitespace-nowrap">
                            @if ($a['status'] === 'Pending')
                                <x-button size="sm" variant="ok" preview="Sprint 5">Approve</x-button>
                                <x-button size="sm" variant="bad" preview="Sprint 5">Decline</x-button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2">Approving sets an expiry date (default 14 days). Declining needs a reason. The student is emailed either way.</p>
</x-layouts.app>
