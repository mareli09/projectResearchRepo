<x-layouts.app title="Backups">
    <x-page-head eyebrow="M9 · Backup and restore" title="Backups"
        lead="The system’s own encrypted backup runs daily at 2:00 AM with an off-server copy. This adds to the ICT Office’s manual server backup twice a day. Restores go to a staging database first.">
        <x-slot:actions><x-button variant="primary" preview="Sprint 9">Back up now</x-button></x-slot:actions>
    </x-page-head>
    <x-preview-note sprint="9" what="These sample backups and Back up now and Restore" />
    <div class="tbl mb-3">
        <table>
            <thead><tr><th>Backup</th><th class="r">Files + DB</th><th class="r">Database</th><th>Encrypted</th><th>Off-server copy</th><th></th></tr></thead>
            <tbody>
                @foreach ($backups as $b)
                    <tr>
                        <td>{{ $b['d'] }}</td><td class="r num">{{ $b['size'] }}</td><td class="r num">{{ $b['db'] }}</td>
                        <td>@if ($b['ok'])<x-pill variant="ok">Yes</x-pill>@else<x-pill variant="bad">Failed</x-pill>@endif</td>
                        <td>@if ($b['off'])<x-pill variant="ok">Copied</x-pill>@else<x-pill variant="bad">Not copied</x-pill>@endif</td>
                        <td class="r">@if ($b['ok'])<x-button size="sm" preview="Sprint 9">Restore to staging</x-button>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <x-callout class="small">The Nov 22 backup failed because the off-server destination was unreachable. Backups since then are complete. Retention: 7 daily, 4 weekly and 3 monthly copies off-server. A restore test runs monthly.</x-callout>
</x-layouts.app>
