<x-layouts.guest title="Activate account">
    <div class="eyebrow">M1 · Activation</div>
    <h1 class="mb-3">Activate account</h1>

    @switch($state)
        @case('ready')
            <x-panel>
                <p class="mb-3">Activate the account for <b>{{ $email }}</b>.</p>
                <form method="POST" action="{{ route('activation.store', $token) }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    @if ($setPassword)
                        <p class="small text-muted">This is a temporary evaluator account. Choose your password. Nobody else sees it.</p>
                        <input type="hidden" name="email" value="{{ $email }}" autocomplete="username">
                        <x-input name="password" type="password" label="Password" autocomplete="new-password" required
                            hint="At least {{ config('tua.auth.password_min_length') }} characters, with a letter and a number." />
                        <x-input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
                    @endif
                    <x-button type="submit" variant="primary" class="self-start">Activate my account</x-button>
                </form>
            </x-panel>
            @break

        @case('used')
            <x-callout>This link was already used. Your account is active.</x-callout>
            <x-button :href="route('login')" variant="primary" class="mt-4">Sign in</x-button>
            @break

        @case('expired')
            <x-callout variant="bad">This activation link has expired.</x-callout>
            <form method="POST" action="{{ route('activation.resend') }}" class="mt-4">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <x-button type="submit" variant="primary">Send a new link</x-button>
            </form>
            @break

        @default
            <x-callout variant="bad">This activation link is not valid. It may have been replaced by a newer link.</x-callout>
            <form method="POST" action="{{ route('activation.resend') }}" class="panel mt-4 flex flex-col gap-3">
                @csrf
                <x-input name="email" type="email" label="TUA email" required />
                <x-button type="submit">Send a new link</x-button>
            </form>
    @endswitch
</x-layouts.guest>
