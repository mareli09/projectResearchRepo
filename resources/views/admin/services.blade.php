<x-layouts.app title="Services and security">
    <x-page-head eyebrow="M9 · Services and security between components" title="Services and security"
        lead="Each link between the core application and the analytics service, the LLM service, the database and the mail server is secured and logged." />
    <x-preview-note sprint="9" what="The call list is sample, and Test, Rotate and Turn on/off" />

    <div class="g2 mb-4">
        <x-panel title="Analytics service (Python)">
            <x-slot:actions><x-pill variant="ok">Signed requests only</x-pill></x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>Listens on {{ parse_url(config('tua.analytics_service.url'), PHP_URL_HOST) }}:{{ parse_url(config('tua.analytics_service.url'), PHP_URL_PORT) }} only, not reachable from the internet</span></li>
                <li><span class="ic">✓</span><span>Accepts only requests signed with the shared HMAC secret and a timestamp under 60 seconds old</span></li>
                <li><span class="ic">✓</span><span>Receives title, abstract and keywords only; returns scores, clusters and top terms</span></li>
                <li><span class="ic warn">!</span><span>1 request with an invalid signature rejected in the last 7 days (sample)</span></li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-2.5"><x-button size="sm" preview="Sprint 9">Test connection</x-button><x-button size="sm" preview="Sprint 9">Rotate secret</x-button></div>
        </x-panel>
        <x-panel title="External LLM service">
            <x-slot:actions>@if ($llmEnabled)<x-pill variant="ok">Reachable</x-pill>@else<x-pill>Turned off</x-pill>@endif</x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>All calls over HTTPS through the Laravel HTTP client</span></li>
                <li><span class="ic">✓</span><span>Service key kept in the server configuration file, never sent to the browser</span></li>
                <li><span class="ic">✓</span><span>Sends only {{ implode(', ', str_replace('_', ' ', $fields)) }}. Never files, full papers or personal data</span></li>
                <li><span class="ic">✓</span><span>Limited to {{ config('tua.llm.rate_per_minute') }} requests per minute; replies are validated and escaped before display</span></li>
                <li><span class="ic">✓</span><span>Suggestions are never saved without a user’s confirmation; every suggestion and action is logged</span></li>
                <li><span class="ic {{ $llmEnabled ? '' : 'warn' }}">{{ $llmEnabled ? '✓' : '!' }}</span><span>Data Privacy Officer approval: {{ $llmEnabled ? 'on file' : 'pending, so suggestions stay off' }}</span></li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-2.5"><x-button size="sm" preview="Sprint 9">Test connection</x-button><x-button size="sm" preview="Sprint 9">{{ $llmEnabled ? 'Turn LLM suggestions off' : 'Turn LLM suggestions on' }}</x-button></div>
        </x-panel>
        <x-panel title="Database (MySQL)">
            <x-slot:actions><x-pill variant="ok">Healthy</x-pill></x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>Application account has only the privileges it needs on its own schema</span></li>
                <li><span class="ic">✓</span><span>Parameterized queries throughout (Eloquent ORM)</span></li>
                <li><span class="ic">✓</span><span>Research files stored outside the public web root; every download is permission-checked and logged</span></li>
                <li><span class="ic">✓</span><span>Passwords as bcrypt hashes; activation tokens and recovery codes stored only as hashes</span></li>
                <li><span class="ic">✓</span><span>Backups encrypted before they leave the server</span></li>
            </ul>
        </x-panel>
        <x-panel title="Mail (SMTP)">
            <x-slot:actions><x-pill variant="warn">2 failed sends (sample)</x-pill></x-slot:actions>
            <ul class="checks">
                <li><span class="ic">✓</span><span>TUA sender address ({{ config('mail.from.address') }}), through the mail service approved by the ICT Office</span></li>
                <li><span class="ic">✓</span><span>Used for activation links, recovery codes and workflow notices; every email is written to the mail log</span></li>
                <li><span class="ic warn">!</span><span>Failed emails retry 3 times, then show in System health</span></li>
            </ul>
            <div class="mt-2.5"><x-button size="sm" preview="Sprint 9">Send a test email</x-button></div>
        </x-panel>
    </div>

    <x-panel title="Recent calls between components">
        <x-slot:actions><a class="small" href="{{ route('activity') }}">Full activity log</a></x-slot:actions>
        <div class="tbl"><table>
            <thead><tr><th>Time</th><th>From → to</th><th>Purpose</th><th>Data sent</th><th>Result</th></tr></thead>
            <tbody>@foreach ($calls as $r)<tr><td class="mono small">{{ $r[0] }}</td><td class="small">{{ $r[1] }}</td><td class="small">{{ $r[2] }}</td><td class="small text-muted">{{ $r[3] }}</td><td><x-pill :variant="$r[4]">{{ $r[5] }}</x-pill></td></tr>@endforeach</tbody>
        </table></div>
    </x-panel>
</x-layouts.app>
