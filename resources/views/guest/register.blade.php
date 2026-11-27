<x-layouts.guest title="Create account" :wide="true">
    <div class="eyebrow">M1 · Authentication</div>
    <h1>Create account</h1>
    <p class="text-muted mb-4">Only <span class="mono">{{ '@'.config('tua.email_domain') }}</span> addresses can register. We will send an activation link to your TUA email.</p>

    @php
        $programsByUnit = $units->mapWithKeys(fn ($u) => [$u->id => $u->programs->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()]);
    @endphp

    <form method="POST" action="{{ route('register') }}" class="panel flex flex-col gap-4" novalidate
          x-data="{ type: @js(old('account_type', 'student')), unit: @js((string) old('academic_unit_id', '')), programs: @js($programsByUnit) }">
        @csrf
        <x-input name="name" label="Full name" autocomplete="name" required />
        <x-input name="email" type="email" label="TUA email" placeholder="juan.delacruz@tua.edu.ph" autocomplete="email" required />

        <fieldset class="flex flex-col gap-1">
            <legend class="font-medium mb-1">I am a</legend>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 min-h-11"><input type="radio" name="account_type" value="student" x-model="type" class="size-5"> Student</label>
                <label class="flex items-center gap-2 min-h-11"><input type="radio" name="account_type" value="faculty" x-model="type" class="size-5"> Faculty</label>
            </div>
            <p class="text-[13px] text-muted">Coordinator and administrator roles are assigned by the System administrator.</p>
            <x-form-error name="account_type" />
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <label for="f-unit" class="font-medium">College</label>
                <select id="f-unit" name="academic_unit_id" x-model="unit" required class="min-h-11 rounded-md border border-line bg-surface px-3">
                    <option value="">Choose your college</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}">{{ $u->code }} · {{ $u->name }}</option>
                    @endforeach
                </select>
                <x-form-error name="academic_unit_id" />
            </div>
            <div class="flex flex-col gap-1" x-show="type === 'student'">
                <label for="f-program" class="font-medium">Program</label>
                <select id="f-program" name="program_id" class="min-h-11 rounded-md border border-line bg-surface px-3" :disabled="!unit">
                    <option value="">Choose your program</option>
                    <template x-for="p in (programs[unit] || [])" :key="p.id">
                        <option :value="p.id" x-text="p.name" :selected="String(p.id) === @js((string) old('program_id'))"></option>
                    </template>
                </select>
                <x-form-error name="program_id" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="password" type="password" label="Password" autocomplete="new-password" required
                hint="At least {{ config('tua.auth.password_min_length') }} characters, with a letter and a number." />
            <x-input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
        </div>

        @if (config('tua.features.recovery_email') || config('tua.features.recovery_sms'))
            <fieldset class="border border-line rounded-md p-3 flex flex-col gap-3">
                <legend class="font-medium px-1">Recovery contact (optional)</legend>
                <p class="text-[13px] text-muted">Helps you get back in if you cannot open your TUA email. You will verify it with a code after you sign in.</p>
                @if (config('tua.features.recovery_email'))
                    <x-input name="recovery_email" type="email" label="Personal email" autocomplete="email" hint="Not your TUA address." />
                @endif
                @if (config('tua.features.recovery_sms'))
                    <x-input name="recovery_mobile" type="tel" label="Mobile number" placeholder="0917 123 4567" autocomplete="tel" />
                @endif
            </fieldset>
        @endif

        <div>
            <label class="flex items-start gap-2 min-h-11">
                <input type="checkbox" name="privacy" value="1" @checked(old('privacy')) class="size-5 mt-0.5">
                <span>I have read the <a href="#privacy" data-open-dialog="privacy">Privacy Notice</a> and agree that the URDC processes my data for the repository, under the Data Privacy Act of 2012 (RA 10173).</span>
            </label>
            <x-form-error name="privacy" />
        </div>

        <x-button type="submit" variant="primary" class="justify-center">Create account</x-button>
        <p class="text-sm">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </form>

    <dialog id="privacy" class="panel max-w-lg m-auto backdrop:bg-black/40">
        <h2 class="mb-2">Privacy Notice</h2>
        <p class="mb-2">The TUA Research Repository collects your name, TUA email, college, program and optional recovery contact to run your account and the research workflow. Your data is used only for the repository, is not shared outside the University, and is kept according to URDC records policy.</p>
        <p class="mb-3 text-muted text-[13px]">Placeholder text. The Data Privacy Officer provides the final notice.</p>
        <form method="dialog"><x-button type="submit">Close</x-button></form>
    </dialog>
</x-layouts.guest>
