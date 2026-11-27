<x-layouts.app title="Home">
    <x-page-head :eyebrow="'Student · '.$college" :title="'Good day, '.\Illuminate\Support\Str::before(auth()->user()->name, ' ')">
        <x-slot:actions><x-button :href="route('student.submit')" variant="primary">Submit a study</x-button></x-slot:actions>
    </x-page-head>

    <div class="g2 mb-4">
        @if ($sub)
            <x-panel>
                <div class="ph mb-2"><h2>Your submission · <span class="mono">{{ $sub['id'] }}</span></h2><x-status-pill :status="$sub['status']" /></div>
                <p class="font-semibold mb-3">{{ $sub['title'] }}</p>
                <x-stepper :sub="$sub" />
                <div class="mt-3.5">@include('partials.screening-callout', ['sub' => $sub, 'note' => 'Your coordinator reviews this before final approval.'])</div>
                <div class="mt-3"><x-button :href="route('student.status', ['id' => $sub['id']])">Open status page</x-button></div>
                @foreach ($returned as $r)
                    <x-callout variant="bad" class="mt-3 small"><b>{{ $r['id'] }} was returned for revision.</b> {{ last($r['history'])[3] ?? '' }}
                        <a href="{{ route('student.status', ['id' => $r['id']]) }}">Revise and resubmit</a></x-callout>
                @endforeach
            </x-panel>
        @else
            <x-empty-state title="No submissions yet">When your terminal paper is approved for submission, send it here with its five files.
                <x-slot:action><x-button :href="route('student.submit')" variant="primary">Submit a study</x-button></x-slot:action>
            </x-empty-state>
        @endif
        <x-panel title="Studies related to your topic">
            <x-slot:actions><a class="small" href="{{ route('search') }}">Search all</a></x-slot:actions>
            @if ($matches)
                <div class="list">
                    @foreach ($matches as $m)
                        <div><a href="{{ route('studies.show', $m['record']['public_id']) }}">{{ $m['record']['title'] }}</a>
                            <div class="meta"><span>{{ $m['record']['college'] }}</span><span>{{ $m['record']['year'] }}</span><span class="mono">{{ number_format($m['score'], 2) }}</span></div></div>
                    @endforeach
                </div>
            @else
                <p class="text-muted">Related studies appear here after your submission is screened.</p>
            @endif
        </x-panel>
    </div>

    <x-panel :title="'Recently published in '.$college">
        <x-slot:actions><a class="small" href="{{ route('student.summary') }}">Research summary</a></x-slot:actions>
        @foreach ($recent as $r)<x-record-row :r="$r" :href="route('studies.show', $r->public_id)" />@endforeach
    </x-panel>
</x-layouts.app>
