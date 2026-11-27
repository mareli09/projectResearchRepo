{{-- Dashboard task mode settings and results (CLAUDE.md §7 M7, SOP 3.3). --}}
<x-layouts.app title="Dashboard tasks">
    <x-page-head eyebrow="M7 · SOP 3.3 · Evaluation" title="Dashboard tasks"
        lead="While task mode is on, each evaluator sees these five tasks one at a time at the top of the page. The time from Start to Done and whether each task was finished are recorded on the server.">
        <x-slot:actions><x-button :href="route('urdc.tasks.export')">Export results (CSV)</x-button></x-slot:actions>
    </x-page-head>

    <div class="split">
        <x-panel title="Results">
            <div class="tbl mb-3"><table>
                <thead><tr><th>Task</th><th class="r">Done</th><th class="r">Not done</th><th class="r">Completion</th><th class="r">Mean time (done)</th></tr></thead>
                <tbody>@foreach ($summary as $no => $s)
                    <tr><td>Task {{ $no }}</td><td class="r num">{{ $s['done'] }}</td><td class="r num">{{ $s['skipped'] }}</td>
                        <td class="r num">{{ $s['rate'] === null ? '—' : number_format($s['rate'], 1).'%' }}</td><td class="r num">{{ $s['avg'] === null ? '—' : number_format($s['avg'], 1).' s' }}</td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="tbl"><table>
                <thead><tr><th>Evaluator</th>@foreach (range(1, \App\Domain\Analytics\DashboardTasks::COUNT) as $n)<th class="r">T{{ $n }}</th>@endforeach<th class="r">Done</th></tr></thead>
                <tbody>
                    @forelse ($results as $r)
                        <tr><td class="small">{{ $r['user'] }}<div class="text-muted">{{ $r['email'] }}</div></td>
                            @foreach (range(1, \App\Domain\Analytics\DashboardTasks::COUNT) as $n)
                                @php $t = $r['tasks'][$n] ?? null; @endphp
                                <td class="r num small">
                                    @if (! $t) —
                                    @elseif ($t['status'] === 'started') <span class="text-muted">open</span>
                                    @elseif ($t['status'] === 'skipped') <span style="color: var(--bad)">not done</span>
                                    @else {{ $t['seconds'] }} s
                                    @endif
                                </td>
                            @endforeach
                            <td class="r num">{{ $r['done'] }} / {{ \App\Domain\Analytics\DashboardTasks::COUNT }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No results yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if ($results !== [])
                <form method="POST" action="{{ route('urdc.tasks.reset') }}" class="mt-3" data-confirm="Clear all task results? Export them first.">@csrf<x-button type="submit" size="sm" variant="bad">Clear results</x-button></form>
            @endif
        </x-panel>

        <x-panel title="Task mode">
            <form method="POST" action="{{ route('urdc.tasks.update') }}" class="flex flex-col gap-3" novalidate>@csrf
                <label class="flex items-center gap-2 min-h-11"><input type="hidden" name="on" value="0"><input type="checkbox" name="on" value="1" class="size-5" @checked(old('on', $config['on']))> Task mode is on</label>
                <fieldset class="flex flex-col gap-1"><legend class="font-medium mb-1">Who does the tasks</legend>
                    <label class="flex items-center gap-2 min-h-11"><input type="radio" name="audience" value="evaluators" class="size-5" @checked(old('audience', $config['audience']) === 'evaluators')> Evaluator accounts only</label>
                    <label class="flex items-center gap-2 min-h-11"><input type="radio" name="audience" value="dashboard_users" class="size-5" @checked(old('audience', $config['audience']) === 'dashboard_users')> Evaluators, coordinators and URDC staff</label>
                </fieldset>
                @foreach ($config['tasks'] as $i => $task)
                    <div class="flex flex-col gap-1"><label for="task-{{ $i }}" class="font-medium">Task {{ $i + 1 }}</label>
                        <textarea id="task-{{ $i }}" name="tasks[]" rows="2" maxlength="300" class="w-full rounded-md border border-line bg-surface px-3 py-2">{{ old('tasks.'.$i, $task) }}</textarea>
                        <x-form-error :name="'tasks.'.$i" /></div>
                @endforeach
                <x-button type="submit" variant="primary">Save</x-button>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
