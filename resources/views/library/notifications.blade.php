<x-layouts.app title="Notifications">
    <x-page-head :eyebrow="'M3 · Notifications · sent to '.$email" title="Notifications" lead="Copies of the emails the system sent you. Every workflow step sends one.">
        @if ($items->total())
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.read') }}">@csrf<x-button type="submit">Mark all as read</x-button></form>
            </x-slot:actions>
        @endif
    </x-page-head>
    <x-panel>
        @forelse ($items as $n)
            <div class="notif">
                <span class="dot2 {{ $n->read_at ? 'read' : '' }}" aria-label="{{ $n->read_at ? 'Read' : 'Unread' }}"></span>
                <div>
                    <div class="ph"><b>{{ $n->data['subject'] ?? 'Notice' }}</b><span class="text-muted small">{{ $n->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</span></div>
                    <p class="small text-muted">{{ $n->data['body'] ?? '' }}</p>
                    @if (! empty($n->data['url']))<a class="small" href="{{ $n->data['url'] }}">Open {{ $n->data['submission'] ?? '' }}</a>@endif
                </div>
            </div>
        @empty
            <x-empty-state title="No notifications yet">Emails about your studies will appear here.</x-empty-state>
        @endforelse
        <div class="mt-3">{{ $items->links() }}</div>
    </x-panel>
</x-layouts.app>
