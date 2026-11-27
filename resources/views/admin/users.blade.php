@php
    use App\Http\Controllers\Admin\UserController as U;
    $canManage = auth()->user()->can('users.manage');
@endphp
<x-layouts.app :title="$canManage ? 'Users and roles' : 'Account help'">
    <div class="eyebrow">M1 · M9 · Administration</div>
    <h1 class="mb-4">{{ $canManage ? 'Users and roles' : 'Account help' }}</h1>

    <form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-56">
            <x-input name="q" label="Search by name or email" :value="$q" type="search" />
        </div>
        <x-button type="submit">Search</x-button>
    </form>

    <div class="flex flex-col gap-3">
        @forelse ($users as $u)
            @php $role = $u->getRoleNames()->first(); @endphp
            <section class="panel" aria-labelledby="u-{{ $u->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="u-{{ $u->id }}">{{ $u->name }}</h2>
                        <div class="text-muted break-all">{{ $u->email }} · {{ $u->academicUnit?->code ?? '—' }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-pill variant="info">{{ U::roleLabel($role) }}</x-pill>
                        @if ($u->deactivated_at)
                            <x-pill variant="bad">Deactivated</x-pill>
                        @elseif (! $u->is_active)
                            <x-pill variant="warn">Waiting for activation</x-pill>
                        @else
                            <x-pill variant="ok">Active</x-pill>
                        @endif
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-end gap-3">
                    @if ($canManage && auth()->user()->isNot($u))
                        <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-col gap-1">
                                <label for="role-{{ $u->id }}" class="text-[13px] text-muted">Role</label>
                                <select id="role-{{ $u->id }}" name="role" class="min-h-11 rounded-md border border-line bg-surface px-3">
                                    @foreach ($roles as $r)
                                        <option value="{{ $r }}" @selected($r === $role)>{{ U::roleLabel($r) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-button type="submit">Assign role</x-button>
                        </form>

                        <form method="POST" action="{{ route($u->deactivated_at ? 'admin.users.reactivate' : 'admin.users.deactivate', $u) }}">
                            @csrf
                            <x-button type="submit" :variant="$u->deactivated_at ? 'ok' : 'bad'">{{ $u->deactivated_at ? 'Reactivate' : 'Deactivate' }}</x-button>
                        </form>
                    @endif

                    @unless ($u->is_active)
                        <form method="POST" action="{{ route('admin.users.resend', $u) }}">
                            @csrf
                            <x-button type="submit">Resend activation</x-button>
                        </form>
                    @endunless
                </div>

                <details class="mt-3" @if ($errors->any() && old('user_id') == $u->id) open @endif>
                    <summary class="cursor-pointer min-h-11 flex items-center">Assisted recovery</summary>
                    <form method="POST" action="{{ route('admin.users.assist', $u) }}" class="mt-2 grid gap-3 sm:grid-cols-2" novalidate>
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                        <x-input name="id_type" :id="'idt-'.$u->id" label="ID checked" placeholder="TUA ID, passport, UMID…" required />
                        <x-input name="recovery_email" :id="'re-'.$u->id" type="email" label="New recovery email" hint="The one-time reset link goes here. Not a TUA address." required />
                        <label class="flex items-start gap-2 min-h-11 sm:col-span-2">
                            <input type="checkbox" name="id_checked" value="1" class="size-5 mt-0.5">
                            <span>I checked a school or government ID in person and it matches this account.</span>
                        </label>
                        <x-form-error name="id_checked" class="sm:col-span-2" />
                        <div class="sm:col-span-2">
                            <x-button type="submit" variant="primary">Send one-time reset link</x-button>
                            <p class="text-[13px] text-muted mt-1">Staff never see or set the password. This step is logged.</p>
                        </div>
                    </form>
                </details>
            </section>
        @empty
            <x-empty-state title="No users found">Try a different name or email.</x-empty-state>
        @endforelse
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
