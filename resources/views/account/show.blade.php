<x-layouts.app title="Account and recovery">
    <div class="eyebrow">M1 · Account</div>
    <h1 class="mb-4">Account and recovery</h1>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-panel title="Your account">
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
                <dt class="text-muted">Name</dt><dd>{{ $user->name }}</dd>
                <dt class="text-muted">TUA email</dt><dd class="break-all">{{ $user->email }}</dd>
                <dt class="text-muted">Role</dt><dd>{{ \App\Http\Controllers\Admin\UserController::roleLabel($user->getRoleNames()->first()) }}</dd>
                <dt class="text-muted">College</dt><dd>{{ $user->academicUnit?->name ?? '—' }}</dd>
                @if ($user->program)<dt class="text-muted">Program</dt><dd>{{ $user->program->name }}</dd>@endif
            </dl>
        </x-panel>

        <x-panel title="Change password">
            @if (session('status') === 'password-updated')
                <x-callout variant="ok" class="mb-3" role="status">Password changed. Other sessions were signed out.</x-callout>
            @endif
            <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-3" novalidate>
                @csrf
                @method('PUT')
                <x-input name="current_password" type="password" label="Current password" bag="updatePassword" autocomplete="current-password" required />
                <x-input name="password" type="password" label="New password" bag="updatePassword" autocomplete="new-password" required
                    hint="At least {{ config('tua.auth.password_min_length') }} characters, with a letter and a number." />
                <x-input name="password_confirmation" type="password" label="Confirm new password" bag="updatePassword" autocomplete="new-password" required />
                <x-button type="submit" variant="primary">Change password</x-button>
            </form>
        </x-panel>

        @if (config('tua.features.recovery_email'))
            <x-panel title="Recovery email">
                @include('account.partials.contact', [
                    'kind' => 'email',
                    'value' => $user->recovery_email,
                    'verified' => $user->recovery_email_verified_at,
                    'field' => 'recovery_email',
                    'type' => 'email',
                    'label' => 'Personal email',
                    'hint' => 'Not your TUA address.',
                ])
            </x-panel>
        @endif

        @if (config('tua.features.recovery_sms'))
            <x-panel title="Recovery mobile">
                @include('account.partials.contact', [
                    'kind' => 'mobile',
                    'value' => $mobileDisplay,
                    'verified' => $user->recovery_mobile_verified_at,
                    'field' => 'recovery_mobile',
                    'type' => 'tel',
                    'label' => 'Mobile number',
                    'hint' => 'For example 0917 123 4567.',
                ])
            </x-panel>
        @endif

        <x-panel title="Recent security events" class="lg:col-span-2">
            @if ($events->isEmpty())
                <p class="text-muted">No events yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-muted text-[13px]"><tr><th class="py-1 pr-4">Time</th><th class="py-1 pr-4">Event</th><th class="py-1 pr-4">What happened</th><th class="py-1">IP address</th></tr></thead>
                        <tbody>
                            @foreach ($events as $e)
                                <tr class="border-t border-line">
                                    <td class="py-2 pr-4 whitespace-nowrap">{{ $e->created_at?->format('M j, Y · g:i A') }}</td>
                                    <td class="py-2 pr-4"><span class="mono text-[13px]">{{ $e->event }}</span></td>
                                    <td class="py-2 pr-4">{{ $e->description }}</td>
                                    <td class="py-2 mono text-[13px]">{{ $e->properties->get('ip') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>
    </div>
</x-layouts.app>
