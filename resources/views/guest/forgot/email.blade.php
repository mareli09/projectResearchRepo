<x-layouts.guest title="Forgot password">
    <div class="eyebrow">M1 · Account recovery · step 1 of 3</div>
    <h1>Forgot password</h1>
    <p class="text-muted mb-4">Enter your TUA email. Next, you choose where to get a 6-digit code.</p>

    <form method="POST" action="{{ route('password.email') }}" class="panel flex flex-col gap-3" novalidate>
        @csrf
        <x-input name="email" type="email" label="TUA email" autocomplete="username" required autofocus />
        <x-button type="submit" variant="primary" class="justify-center">Continue</x-button>
        <a href="{{ route('login') }}" class="text-sm">Back to sign in</a>
    </form>

    <p class="text-[13px] text-muted mt-4">Cannot use any of your contacts? Visit the URDC office with a school or government ID for assisted recovery.</p>
</x-layouts.guest>
