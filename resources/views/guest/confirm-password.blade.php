<x-layouts.guest title="Confirm password">
    <h1 class="mb-4">Confirm your password</h1>
    <form method="POST" action="{{ route('password.confirm') }}" class="panel flex flex-col gap-3">
        @csrf
        <x-input name="password" type="password" label="Password" autocomplete="current-password" required autofocus />
        <x-button type="submit" variant="primary" class="justify-center">Confirm</x-button>
    </form>
</x-layouts.guest>
