{{-- "Services and security" (CLAUDE.md §3, §7 M9): each link between components, tests, and the last calls. --}}
<x-layouts.app title="Services and security">
    <x-page-head eyebrow="M9 · Services and security between components" title="Services and security"
        lead="Each link between the core application and the analytics service, the LLM service, the database and the mail server is secured and logged." />

    <div class="g2 mb-4">
        <x-panel title="Analytics service (Python)">
            <x-slot:actions><x-pill variant="ok">Signed requests only</x-pill></x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>Listens on {{ parse_url(config('tua.analytics_service.url'), PHP_URL_HOST) }}:{{ parse_url(config('tua.analytics_service.url'), PHP_URL_PORT) }} only, not reachable from the internet</span></li>
                <li><span class="ic">✓</span><span>Accepts only requests signed with the shared HMAC secret and a timestamp under 60 seconds old; others get 401 and are not processed</span></li>
                <li><span class="ic">✓</span><span>Receives title, abstract and keywords only; returns scores, clusters and top terms. No database access</span></li>
            </ul>
            <form method="POST" action="{{ route('admin.services.test', 'analytics') }}" class="flex flex-wrap gap-2 mt-2.5">@csrf<x-button type="submit" size="sm">Test connection</x-button></form>
            <p class="small text-muted mt-2">To rotate the secret, change <span class="mono">ANALYTICS_HMAC_SECRET</span> in the server's <span class="mono">.env</span> and restart the analytics service (deploy/README).</p>
        </x-panel>

        <x-panel title="External LLM service">
            <x-slot:actions>@if ($llmEnabled)<x-pill variant="ok">Turned on</x-pill>@else<x-pill>Turned off</x-pill>@endif</x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>All calls over HTTPS through the Laravel HTTP client ({{ config('tua.llm.provider') ?: 'no provider set' }}{{ config('tua.llm.model') ? ', '.config('tua.llm.model') : '' }})</span></li>
                <li><span class="ic">✓</span><span>Service key kept in the server configuration file, never sent to the browser</span></li>
                <li><span class="ic">✓</span><span>Sends only {{ implode(', ', str_replace('_', ' ', $fields)) }}. Never files, full papers or personal data</span></li>
                <li><span class="ic">✓</span><span>Limited to {{ \App\Domain\Admin\SystemSettings::llmRate() }} requests per minute with an {{ \App\Domain\Admin\SystemSettings::llmTimeout() }}-second timeout; replies are validated and escaped before display</span></li>
                <li><span class="ic">✓</span><span>Suggestions are never saved without a user’s confirmation; every suggestion and action is logged</span></li>
                <li><span class="ic {{ $dpo ? '' : 'warn' }}">{{ $dpo ? '✓' : '!' }}</span><span>Data Privacy Officer approval: {{ $dpo ? 'dated '.$dpo : 'not recorded yet (set LLM_DPO_APPROVED_ON in .env when approved)' }}</span></li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-2.5">
                <form method="POST" action="{{ route('admin.services.test', 'llm') }}">@csrf<x-button type="submit" size="sm">Test connection</x-button></form>
                <x-button size="sm" :href="route('admin.settings').'#llm'">{{ $llmEnabled ? 'Turn LLM suggestions off' : 'LLM settings' }}</x-button>
            </div>
            <p class="small text-muted mt-2">The test sends a fixed sample text, not repository data.</p>
        </x-panel>

        <x-panel title="Database ({{ config('database.default') === 'mysql' ? 'MySQL' : config('database.default') }})">
            <x-slot:actions><x-pill variant="ok">Connected</x-pill></x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>Application account should have only DML rights on its own schema; migrations run with a separate account</span></li>
                <li><span class="ic">✓</span><span>Parameterized queries throughout (Eloquent ORM and the query builder)</span></li>
                <li><span class="ic">✓</span><span>Research files stored outside the public web root; every download is permission-checked and logged</span></li>
                <li><span class="ic">✓</span><span>Passwords as bcrypt hashes; activation tokens and recovery codes stored only as hashes</span></li>
            </ul>
        </x-panel>

        <x-panel title="Mail (SMTP)">
            <x-slot:actions><x-pill :variant="$mail['failed'] ? 'warn' : 'ok'">{{ $mail['failed'] ? $mail['failed'].' failed sends' : 'No failed sends' }}</x-pill></x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>TUA sender address ({{ config('mail.from.address') }}) through the {{ config('mail.default') }} mailer{{ config('mail.default') === 'log' ? ': emails are written to the log file, not sent' : '' }}</span></li>
                <li><span class="ic">✓</span><span>Used for activation links, recovery codes and workflow notices; every email is written to the mail log</span></li>
                <li><span class="ic">✓</span><span>Failed emails retry 3 times, then show in System health</span></li>
            </ul>
            <form method="POST" action="{{ route('admin.services.test', 'mail') }}" class="mt-2.5">@csrf<x-button type="submit" size="sm">Send a test email to me</x-button></form>
        </x-panel>
    </div>

    <x-panel title="Recent calls between components">
        <x-slot:actions><a class="small" href="{{ route('activity', ['event' => 'service']) }}">Full activity log</a></x-slot:actions>
        <div class="tbl"><table>
            <thead><tr><th>Time</th><th>From → to</th><th>Purpose</th><th>Data sent</th><th>Result</th></tr></thead>
            <tbody>
                @forelse ($calls as $r)<tr><td class="mono small whitespace-nowrap">{{ $r['when'] }}</td><td class="small">{{ $r['service'] }}</td><td class="small">{{ $r['purpose'] }}</td><td class="small text-muted">{{ $r['fields'] }}</td><td><x-pill :variant="$r['ok'] ? 'ok' : 'bad'">{{ $r['result'] }}</x-pill></td></tr>
                @empty<tr><td colspan="5" class="text-muted">No calls logged yet.</td></tr>@endforelse
            </tbody>
        </table></div>
    </x-panel>
</x-layouts.app>
