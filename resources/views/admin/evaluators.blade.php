{{-- "Evaluator accounts" (CLAUDE.md §7 M9, §17): evaluation copy only. --}}
<x-layouts.app title="Evaluator accounts">
    <x-page-head eyebrow="M1 · M9 · Evaluation copy" title="Evaluator accounts"
        lead="Temporary accounts for external IT experts on the evaluation site. They see sample and anonymized records only, expire automatically, and every action is logged." />

    @unless ($isEvalCopy)
        <x-callout variant="gold" class="mb-4">This is not the evaluation copy (<span class="mono">APP_EVALUATION_COPY=false</span>). Evaluator accounts can only be created on the evaluation site, and they cannot sign in here.</x-callout>
    @endunless

    <div class="split">
        <div class="tbl">
            <table>
                <thead><tr><th>Evaluator</th><th>Acts as</th><th>Created</th><th>Expires</th><th>Status</th><th class="r">Logged actions</th><th></th></tr></thead>
                <tbody>
                    @forelse ($evaluators as $e)
                        <tr>
                            <td>{{ $e['label'] }}<div class="small text-muted">{{ $e['user']->email }}</div></td>
                            <td class="small">{{ $e['role'] }}</td>
                            <td class="small whitespace-nowrap">{{ $e['created'] }}</td>
                            <td class="small whitespace-nowrap">{{ $e['expires'] }}</td>
                            <td><x-pill :variant="$e['tone']">{{ $e['status'] }}</x-pill></td>
                            <td class="r num">
                                <a href="{{ route('activity', ['user' => $e['user']->email]) }}">{{ number_format($e['actions']) }}</a>
                            </td>
                            <td class="r whitespace-nowrap">
                                @if ($e['status'] === 'Waiting for activation')
                                    <form method="POST" action="{{ route('admin.users.resend', $e['user']) }}" class="inline">@csrf<x-button type="submit" size="sm">Resend link</x-button></form>
                                @endif
                                @if ($e['status'] !== 'Expired')
                                    <form method="POST" action="{{ route('admin.evaluators.end', $e['user']) }}" class="inline" x-data
                                        @submit="if (! confirm('End this evaluator account now? It is signed out at once.')) $event.preventDefault()">@csrf
                                        <x-button type="submit" size="sm" variant="bad">End now</x-button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No evaluator accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-panel title="New evaluator">
            <form method="POST" action="{{ route('admin.evaluators.store') }}" class="flex flex-col gap-2.5 mt-2" novalidate
                x-data="{ role: @js(old('role', \App\Models\User::ROLE_URDC)), unit: @js((string) old('academic_unit_id', '')) }">
                @csrf
                <fieldset @disabled(! $isEvalCopy) class="flex flex-col gap-2.5">
                    <x-input name="label" label="Name or label" :value="old('label', 'IT expert '.str_pad((string) (count($evaluators) + 1), 2, '0', STR_PAD_LEFT))" required />
                    <x-input name="email" type="email" label="Evaluator’s email" :value="old('email')" required hint="Any address. The activation link goes here." />

                    <div class="flex flex-col gap-1">
                        <label for="ev-role" class="font-medium">Acts as</label>
                        <select id="ev-role" name="role" x-model="role" class="min-h-11 rounded-md border border-line bg-surface px-3">
                            @foreach ($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <x-form-error name="role" />
                    </div>

                    <div class="flex flex-col gap-1" x-show="role !== @js(\App\Models\User::ROLE_URDC)">
                        <label for="ev-unit" class="font-medium">College</label>
                        <select id="ev-unit" name="academic_unit_id" x-model="unit" class="min-h-11 rounded-md border border-line bg-surface px-3">
                            <option value="">Choose a college</option>
                            @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->code }} · {{ $u->name }}</option>@endforeach
                        </select>
                        <x-form-error name="academic_unit_id" />
                    </div>

                    <div class="flex flex-col gap-1" x-show="role === @js(\App\Models\User::ROLE_STUDENT)">
                        <label for="ev-program" class="font-medium">Program</label>
                        <select id="ev-program" name="program_id" class="min-h-11 rounded-md border border-line bg-surface px-3">
                            <option value="">Choose a program</option>
                            @foreach ($programs as $p)
                                <option value="{{ $p->id }}" x-show="unit === '{{ $p->academic_unit_id }}'" @selected((string) old('program_id') === (string) $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <x-form-error name="program_id" />
                    </div>

                    <x-input name="expires_on" type="date" label="Expires on" :value="old('expires_on', $defaultExpiry)" :max="$latestExpiry" required
                        hint="At the end of this day the account stops working." />

                    <x-button type="submit" variant="primary">Create account</x-button>
                </fieldset>
                <p class="small text-muted">The evaluator gets a one-time link (48 hours) to set their own password. You never see it. Every action is logged, and accounts cannot exist in production.</p>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
