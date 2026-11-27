<x-layouts.guest title="Sign in">
    <div class="eyebrow">M1 · Authentication</div>
    <h1>Sign in</h1>
    <p class="text-muted mb-4">Use your TUA email address.</p>

    <form method="POST" action="{{ route('login') }}" class="panel flex flex-col gap-3" novalidate>
        @csrf
        <x-input name="email" type="email" label="TUA email" placeholder="juan.delacruz@tua.edu.ph" autocomplete="username" required autofocus />
        <x-input name="password" type="password" label="Password" autocomplete="current-password" required />
        <label class="flex items-center gap-2 min-h-11">
            <input type="checkbox" name="remember" value="1" class="size-5"> Keep me signed in on this device
        </label>
        <x-button type="submit" variant="primary" class="justify-center">Sign in</x-button>
        <div class="flex justify-between text-sm">
            <a href="{{ route('password.request') }}">Forgot password?</a>
            <a href="{{ route('register') }}">Create account</a>
        </div>
    </form>

    @if (request()->boolean('inactive'))
        <form method="POST" action="{{ route('activation.resend') }}" class="panel mt-4 flex flex-col gap-3">
            @csrf
            <p>Did not get the activation email, or the link expired?</p>
            <input type="hidden" name="email" value="{{ old('email') }}">
            <x-button type="submit">Send a new link</x-button>
        </form>
    @endif

    <p class="text-[13px] text-muted mt-4">
        After {{ config('tua.auth.login_max_attempts') }} failed attempts, sign-in is paused for 1 minute.
        Sessions end after {{ config('session.lifetime') }} minutes without activity.
    </p>
</x-layouts.guest>
