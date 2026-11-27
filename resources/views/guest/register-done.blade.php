<x-layouts.guest title="Check your email">
    <div class="eyebrow">M1 · Activation</div>
    <h1 class="mb-3">Check your TUA email</h1>
    <x-panel>
        <p>We sent an activation link to <b>{{ $email ?? 'your TUA email' }}</b>.</p>
        <p class="mt-2 text-muted">Open it within {{ config('tua.auth.activation_link_hours') }} hours. Your account stays inactive until you do.</p>
    </x-panel>
    @if ($email)
        <form method="POST" action="{{ route('activation.resend') }}" class="mt-4">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <x-button type="submit">Send a new link</x-button>
        </form>
    @endif
</x-layouts.guest>
