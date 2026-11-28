<x-layouts.app title="Activity log">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <div class="eyebrow">M8 · Monitoring</div>
            <h1>Activity log</h1>
            <p class="small text-muted mt-1">Entries cannot be edited or deleted. They are kept for {{ \App\Domain\Admin\SystemSettings::logRetentionYears() }} {{ \Illuminate\Support\Str::plural('year', \App\Domain\Admin\SystemSettings::logRetentionYears()) }} (System settings), then removed automatically.</p>
        </div>
        <x-button :href="route('activity.export', $filters)">Export CSV</x-button>
    </div>

    <form method="GET" class="panel mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
        <div class="flex flex-col gap-1">
            <label for="f-event" class="font-medium">Event</label>
            <select id="f-event" name="event" class="min-h-11 rounded-md border border-line bg-surface px-3">
                <option value="">All events</option>
                @foreach ($events as $e)
                    <option value="{{ $e }}" @selected(($filters['event'] ?? '') === $e)>{{ $e }}</option>
                @endforeach
            </select>
        </div>
        <x-input name="user" label="User (name or email)" :value="$filters['user'] ?? ''" />
        <x-input name="from" type="date" label="From" :value="$filters['from'] ?? ''" />
        <x-input name="to" type="date" label="To" :value="$filters['to'] ?? ''" />
        <x-button type="submit" variant="primary" class="justify-center">Filter</x-button>
    </form>

    <div class="panel overflow-x-auto p-0">
        <table class="w-full text-left">
            <thead class="text-muted text-[13px] bg-surface-2">
                <tr>
                    <th class="py-2 px-3">Time (Asia/Manila)</th><th class="py-2 px-3">Event</th><th class="py-2 px-3">User</th>
                    <th class="py-2 px-3">What happened</th><th class="py-2 px-3">Details</th><th class="py-2 px-3">IP address</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $a)
                    <tr class="border-t border-line align-top">
                        <td class="py-2 px-3 whitespace-nowrap">{{ $a->created_at?->format('M j, Y · g:i:s A') }}</td>
                        <td class="py-2 px-3"><span class="mono text-[13px]">{{ $a->event }}</span></td>
                        <td class="py-2 px-3">{{ $a->causer?->name ?? '—' }}<div class="text-[13px] text-muted break-all">{{ $a->causer?->email }}</div></td>
                        <td class="py-2 px-3">{{ $a->description }}</td>
                        <td class="py-2 px-3 text-[13px]">
                            @foreach (['details', 'old', 'new'] as $part)
                                @if ($a->properties->get($part))
                                    <div><span class="text-muted">{{ $part }}:</span> <span class="mono">{{ json_encode($a->properties->get($part)) }}</span></div>
                                @endif
                            @endforeach
                        </td>
                        <td class="py-2 px-3 mono text-[13px]">{{ $a->properties->get('ip') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 px-3 text-center text-muted">No entries match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-[13px] text-muted mt-2">Entries cannot be edited or deleted.</p>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.app>
