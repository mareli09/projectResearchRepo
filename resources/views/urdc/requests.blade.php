@php $in = 'w-full min-h-11 rounded-md border border-line bg-surface px-3'; @endphp
<x-layouts.app title="Full-paper requests">
    <x-page-head eyebrow="M4 · Access to full papers" title="Full-paper requests"
        lead="Abstracts are open to everyone signed in. Students get a full paper when the URDC approves a request, following University policy. Approved downloads are permission-checked and logged." />

    <div class="flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter by status">
        @foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined'] as $k => $l)
            <x-button size="sm" :variant="($status ?? '') === $k ? 'primary' : 'default'" :href="route('urdc.requests', array_filter(['status' => $k]))">{{ $l }}@if ($k === 'pending' && $pending) ({{ $pending }})@endif</x-button>
        @endforeach
    </div>
    <x-form-error name="decision" class="mb-3" />

    <div class="tbl">
        <table>
            <thead><tr><th>Request</th><th>Requested by</th><th>Study</th><th>Purpose</th><th>Date</th><th>Status</th><th>Decision</th></tr></thead>
            <tbody>
                @forelse ($requests as $a)
                    <tr>
                        <td class="mono small">{{ $a->public_id }}</td>
                        <td>{{ $a->user->name }}<div class="text-muted small">{{ \Illuminate\Support\Str::headline($a->user->getRoleNames()->first() ?? '') }}{{ $a->user->academicUnit ? ' · '.$a->user->academicUnit->code : '' }}</div></td>
                        <td class="small"><a href="{{ route('studies.show', $a->record->public_id) }}">{{ $a->record->public_id }}</a><div class="text-muted">{{ \Illuminate\Support\Str::limit($a->record->title, 60) }}</div></td>
                        <td class="small" style="max-width: 280px">{{ $a->purpose }}</td>
                        <td class="small whitespace-nowrap">{{ $a->created_at->format('M j, Y') }}</td>
                        <td><x-pill :variant="['approved' => 'ok', 'declined' => 'bad'][$a->status] ?? 'warn'">{{ ucfirst($a->status) }}</x-pill></td>
                        <td class="small" style="min-width: 260px">
                            @if ($a->status === 'pending')
                                <div x-data="{ mode: '' }">
                                    <div class="flex flex-wrap gap-2" x-show="mode === ''">
                                        <x-button size="sm" variant="ok" x-on:click="mode = 'approve'">Approve</x-button>
                                        <x-button size="sm" variant="bad" x-on:click="mode = 'decline'">Decline</x-button>
                                    </div>
                                    <form method="POST" action="{{ route('urdc.requests.decide', $a) }}" x-show="mode === 'approve'" x-cloak class="flex flex-col gap-2">
                                        @csrf <input type="hidden" name="decision" value="approve">
                                        <label for="ex-{{ $a->id }}" class="font-medium">Access until</label>
                                        <input id="ex-{{ $a->id }}" type="date" name="expires_at" class="{{ $in }}" value="{{ $defaultExpiry }}" min="{{ now()->addDay()->toDateString() }}" required>
                                        <div class="flex gap-2"><x-button type="submit" size="sm" variant="ok">Approve and email</x-button><x-button size="sm" x-on:click="mode = ''">Cancel</x-button></div>
                                    </form>
                                    <form method="POST" action="{{ route('urdc.requests.decide', $a) }}" x-show="mode === 'decline'" x-cloak class="flex flex-col gap-2">
                                        @csrf <input type="hidden" name="decision" value="decline">
                                        <label for="rs-{{ $a->id }}" class="font-medium">Reason (the student sees it)</label>
                                        <textarea id="rs-{{ $a->id }}" name="reason" rows="2" class="{{ $in }} py-2" required minlength="5"></textarea>
                                        <div class="flex gap-2"><x-button type="submit" size="sm" variant="bad">Decline and email</x-button><x-button size="sm" x-on:click="mode = ''">Cancel</x-button></div>
                                    </form>
                                </div>
                            @elseif ($a->status === 'approved')
                                Until {{ $a->expires_at?->format('M j, Y') ?? '—' }}@if ($a->expires_at?->isPast()) <x-pill>expired</x-pill>@endif
                            @else
                                {{ $a->reason }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No requests{{ $status ? ' with this status' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $requests->links() }}</div>
    <p class="small text-muted mt-2">Approving sets an expiry date (default {{ \App\Domain\Repository\AccessRequests::DEFAULT_DAYS }} days). Declining needs a reason. The student is emailed either way.</p>
</x-layouts.app>
