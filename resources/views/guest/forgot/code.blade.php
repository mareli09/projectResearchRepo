<x-layouts.guest title="Set a new password">
    <div class="eyebrow">M1 · Account recovery · step 3 of 3</div>
    <h1>Enter the code and a new password</h1>
    <p class="text-muted mb-4">The code works once. After {{ config('tua.auth.recovery_code_max_attempts') }} wrong tries it stops working.</p>

    <form method="POST" action="{{ route('password.code.reset') }}" class="panel flex flex-col gap-3" novalidate>
        @csrf
        <x-input name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" class="mono tracking-[0.3em]" required autofocus />
        <x-input name="password" type="password" label="New password" autocomplete="new-password" required
            hint="At least {{ config('tua.auth.password_min_length') }} characters, with a letter and a number." />
        <x-input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
        <x-button type="submit" variant="primary" class="justify-center">Set new password</x-button>
        <p class="text-[13px] text-muted">Setting a new password signs you out everywhere else.</p>
    </form>

    <form method="POST" action="{{ route('password.send-code') }}" class="mt-3">
        @csrf
        <input type="hidden" name="channel" value="{{ session('recovery.channel') }}">
        <x-button type="submit" size="sm">Send a new code</x-button>
    </form>
</x-layouts.guest>
