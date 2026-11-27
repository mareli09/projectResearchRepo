{{-- "Backups" (CLAUDE.md §7 M9): live list of encrypted backups, Back up now, restore to staging. --}}
<x-layouts.app title="Backups">
    <x-page-head eyebrow="M9 · Backup and restore" title="Backups"
        lead="An encrypted backup of the database and research files runs daily at {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $dailyAt)->format('g:i A') }}, with an off-server copy. This adds to the ICT Office’s manual server backup twice a day. Restores go to a staging database first.">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.backups.run') }}">@csrf
                <x-button type="submit" variant="primary" :disabled="! $encryptionReady || $busy !== null">Back up now</x-button>
            </form>
        </x-slot:actions>
    </x-page-head>

    @error('backup')<x-callout variant="bad" class="mb-4" role="alert">{{ $message }}</x-callout>@enderror
    @if ($busy !== null)
        <x-callout class="mb-4" role="status">Running now: {{ $busy }}. Refresh this page to see the result.</x-callout>
    @endif
    @unless ($encryptionReady)
        <x-callout variant="bad" class="mb-4"><b>Backups are off.</b> Set <span class="mono">BACKUP_ARCHIVE_PASSWORD</span> in the server’s <span class="mono">.env</span>. Every backup is encrypted with AES-256, so none is made without a password. Keep the password somewhere safe outside the server: without it a backup cannot be restored.</x-callout>
    @endunless
    @unless ($offsite)
        <x-callout variant="gold" class="mb-4"><b>No off-server copy.</b> Set <span class="mono">BACKUP_OFFSITE_PATH</span> to a share that is mounted from outside this server. Until then, backups are kept on this server only.</x-callout>
    @endunless

    <div class="tbl mb-4">
        <table>
            <thead><tr><th>Backup</th><th class="r">Files + DB</th><th class="r">Database</th><th class="r">Research files</th><th>Encrypted</th><th>Off-server copy</th><th></th></tr></thead>
            <tbody>
                @forelse ($backups as $b)
                    <tr>
                        <td>{{ $b['date'] }}<div class="mono small text-muted">{{ $b['file'] }}</div></td>
                        <td class="r num">{{ $b['size'] }}</td>
                        <td class="r num">{{ $b['db'] }}</td>
                        <td class="r num">{{ number_format($b['files']) }}</td>
                        <td>@if ($b['encrypted'])<x-pill variant="ok">Yes</x-pill>@else<x-pill variant="bad">No</x-pill>@endif</td>
                        <td>@if ($b['offsite'])<x-pill variant="ok">Copied</x-pill>@elseif ($offsite)<x-pill variant="bad">Not copied</x-pill>@else<x-pill>Not set up</x-pill>@endif</td>
                        <td class="r">
                            <form method="POST" action="{{ route('admin.backups.restore', $b['file']) }}">@csrf
                                <x-button type="submit" size="sm" :disabled="$busy !== null || $staging === null">Restore to staging</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state title="No backups yet">The first one is made at {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $dailyAt)->format('g:i A') }}, or now with “Back up now”.</x-empty-state></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="g2">
        <x-panel title="Last restore check">
            @if ($lastRestore)
                <p class="mb-2">
                    <x-pill :variant="$lastRestore['ok'] ? 'ok' : 'bad'">{{ $lastRestore['ok'] ? 'Passed' : 'Failed' }}</x-pill>
                    {{ ($lastRestore['kind'] ?? '') === 'monthly' ? 'Monthly restore test' : 'Restore to staging' }}
                    of <span class="mono small">{{ $lastRestore['file'] }}</span>
                    · {{ \App\Domain\Admin\Backups::formatDate($lastRestore['at'] ?? null) }}
                    @if (! empty($lastRestore['by'])) · by {{ $lastRestore['by'] }}@endif
                    · {{ $lastRestore['seconds'] ?? 0 }} s
                </p>
                @if (! $lastRestore['ok'])
                    <x-callout variant="bad" class="small">{{ $lastRestore['error'] }}</x-callout>
                @else
                    <div class="tbl"><table>
                        <thead><tr><th>Table</th><th class="r">In the backup</th><th class="r">Live now</th></tr></thead>
                        <tbody>@foreach ($lastRestore['tables'] as $name => $t)<tr><td class="mono small">{{ $name }}</td><td class="r num">{{ number_format((int) $t['backup']) }}</td><td class="r num">{{ $t['live'] === null ? '—' : number_format((int) $t['live']) }}</td></tr>@endforeach</tbody>
                    </table></div>
                    <p class="small text-muted mt-2">The backup holds {{ number_format((int) $lastRestore['files_in_backup']) }} research files for {{ number_format((int) $lastRestore['files_in_records']) }} file records. Live counts are higher when records were added after the backup.</p>
                    @if (! empty($lastRestore['note']))<p class="small text-muted mt-1">{{ $lastRestore['note'] }}</p>@endif
                @endif
            @else
                <p class="text-muted">No restore has been tested yet. Restore a backup to staging to check it, or wait for the monthly test.</p>
            @endif
        </x-panel>

        <x-panel title="How restores work">
            <ol class="list-decimal pl-5 flex flex-col gap-1.5 small">
                <li><b>Restore to staging</b> loads the backup into the staging database <span class="mono">{{ $staging ?? 'not set' }}</span> and compares its tables with the live database. Live data is not touched.</li>
                <li>When the check passes, the ICT Office promotes it on the server: <span class="mono">php artisan backup:promote &lt;file&gt;</span>. It takes a safety backup first and puts the site in maintenance mode while it runs.</li>
                <li>A restore test runs on the 1st of every month at 4:00 AM with the newest backup.</li>
            </ol>
            <p class="small text-muted mt-2">Retention: 7 daily, 4 weekly and 3 monthly copies. Failures email the System administrators and appear in the activity log.</p>
            @if ($failures !== [])
                <h3 class="font-semibold mt-3 mb-1">Recent failures</h3>
                <ul class="small flex flex-col gap-1">@foreach ($failures as $f)<li><span class="text-muted">{{ $f['when'] }}</span> · {{ $f['error'] }}</li>@endforeach</ul>
            @endif
        </x-panel>
    </div>
</x-layouts.app>
