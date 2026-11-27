<x-layouts.guest title="Set a new password">
    <div class="eyebrow">M1 · Assisted recovery</div>
    <h1 class="mb-4">Set a new password</h1>

    <form method="POST" action="{{ route('password.update.link') }}" class="panel flex flex-col gap-3" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" label="TUA email" :value="$email" autocomplete="username" required readonly />
        <x-input name="password" type="password" label="New password" autocomplete="new-password" required
            hint="At least {{ config('tua.auth.password_min_length') }} characters, with a letter and a number." />
        <x-input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
        <x-button type="submit" variant="primary" class="justify-center">Set new password</x-button>
    </form>
</x-layouts.guest>
