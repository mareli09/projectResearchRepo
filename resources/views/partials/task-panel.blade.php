{{-- Dashboard task mode (CLAUDE.md §7 M7, SOP 3.3): the evaluator's current task, timed on the server. --}}
@php
    $tUser = auth()->user();
    $tOn = $tUser && \App\Domain\Analytics\DashboardTasks::appliesTo($tUser);
    if ($tOn) {
        $tNo = \App\Domain\Analytics\DashboardTasks::current($tUser);
        $tRun = $tNo ? \App\Domain\Analytics\DashboardTasks::runsOf($tUser)->get($tNo) : null;
        $tText = $tNo ? \App\Domain\Analytics\DashboardTasks::config()['tasks'][$tNo - 1] : null;
    }
@endphp
@if ($tOn)
    <section class="callout callout-gold mb-4" aria-label="Evaluation task" data-task-panel>
        @if ($tNo === null)
            <b>Evaluation tasks finished.</b> Thank you. You can keep using the system.
        @else
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="eyebrow">Evaluation task {{ $tNo }} of {{ \App\Domain\Analytics\DashboardTasks::COUNT }}</div>
                    <p class="font-semibold mt-0.5">{{ $tText }}</p>
                    @if ($tRun)<p class="small text-muted mt-0.5">Started {{ $tRun->started_at->timezone(config('app.timezone'))->format('g:i:s A') }}. The time is measured from Start to Done.</p>@endif
                </div>
                @unless ($tRun)
                    <form method="POST" action="{{ route('tasks.start', $tNo) }}">@csrf<x-button type="submit" variant="primary">Start task {{ $tNo }}</x-button></form>
                @endunless
            </div>
            @if ($tRun)
                <form method="POST" action="{{ route('tasks.finish', $tNo) }}" class="mt-2.5 flex flex-wrap items-end gap-2">@csrf
                    <div class="flex flex-col gap-1 grow min-w-0"><label for="task-answer" class="small font-medium">Your answer (optional)</label>
                        <input id="task-answer" name="answer" maxlength="500" class="min-h-11 rounded-md border border-line bg-surface px-3"></div>
                    <x-button type="submit" name="status" value="done" variant="ok">Done</x-button>
                    <x-button type="submit" name="status" value="skipped">I could not do it</x-button>
                </form>
            @endif
        @endif
    </section>
@endif
