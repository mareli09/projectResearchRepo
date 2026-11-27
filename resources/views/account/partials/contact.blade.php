{{-- One recovery contact: show, verify with a code, change or remove. --}}
@if ($value)
    <p class="mb-3 flex flex-wrap items-center gap-2">
        <span class="break-all">{{ $value }}</span>
        @if ($verified)
            <x-pill variant="ok">Verified</x-pill>
        @else
            <x-pill variant="warn">Not verified</x-pill>
        @endif
    </p>

    @unless ($verified)
        <form method="POST" action="{{ route('account.contact.verify', $kind) }}" class="flex flex-col gap-2 mb-3" novalidate>
            @csrf
            <x-input name="code" :id="'code-'.$kind" :error-key="'code_'.$kind" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="mono tracking-[0.3em]" required />
            <div class="flex flex-wrap gap-2">
                <x-button type="submit" variant="primary">Verify</x-button>
                <x-button type="submit" :formaction="route('account.contact.resend', $kind)">Send a new code</x-button>
            </div>
        </form>
        <p class="text-[13px] text-muted mb-3">An unverified contact cannot receive recovery codes.</p>
    @endunless
@else
    <p class="text-muted mb-3">None yet.</p>
@endif

<details @if ($errors->has($field)) open @endif>
    <summary class="cursor-pointer min-h-11 flex items-center">{{ $value ? 'Change' : 'Add' }} recovery {{ $kind }}</summary>
    <form method="POST" action="{{ route('account.contact.store', $kind) }}" class="flex flex-col gap-2 mt-2" novalidate>
        @csrf
        <x-input :name="$field" :type="$type" :label="$label" :hint="$hint" required />
        <x-button type="submit">Save and send code</x-button>
    </form>
</details>

@if ($value)
    <form method="POST" action="{{ route('account.contact.destroy', $kind) }}" class="mt-3">
        @csrf
        @method('DELETE')
        <x-button type="submit" variant="bad" size="sm">Remove</x-button>
    </form>
@endif
