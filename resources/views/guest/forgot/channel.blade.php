<x-layouts.guest title="Choose where to get your code">
    <div class="eyebrow">M1 · Account recovery · step 2 of 3</div>
    <h1>Where should we send the code?</h1>
    <p class="text-muted mb-4">Only part of each contact is shown.</p>

    @php
        $labels = ['tua_email' => 'TUA email', 'recovery_email' => 'Recovery email', 'sms' => 'Text message (SMS)'];
    @endphp
    <form method="POST" action="{{ route('password.send-code') }}" class="panel flex flex-col gap-2">
        @csrf
        <fieldset class="flex flex-col gap-2">
            <legend class="sr-only">Channel</legend>
            @foreach ($channels as $channel => $masked)
                <label class="flex items-center gap-3 min-h-11 border border-line rounded-md px-3">
                    <input type="radio" name="channel" value="{{ $channel }}" @checked($loop->first) class="size-5">
                    <span><b>{{ $labels[$channel] }}</b> <span class="mono text-muted">{{ $masked }}</span></span>
                </label>
            @endforeach
        </fieldset>
        <x-form-error name="channel" />
        <x-button type="submit" variant="primary" class="justify-center mt-2">Send code</x-button>
        <a href="{{ route('password.request') }}" class="text-sm">Use a different email</a>
    </form>
</x-layouts.guest>
