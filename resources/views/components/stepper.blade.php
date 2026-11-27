{{-- Four-step workflow stepper (CLAUDE.md §7 M3). --}}
@props(['sub'])
@php
    $ret = in_array($sub['status'], ['Returned', 'Rejected'], true);
    $keys = ['Submitted', 'Endorsed', 'Reviewed', 'Published'];
@endphp
<ol class="steps" aria-label="Workflow progress">
    @foreach (\App\Domain\Site\DesignData::STEPS as $i => $label)
        @php
            $n = $i + 1;
            $cls = $n <= $sub['step'] ? 'done' : '';
            if (! $ret && $n === $sub['step'] + 1) {
                $cls = 'cur';
            }
            if ($ret && $n === $sub['step'] + 1) {
                $cls = 'ret';
            }
            $h = collect($sub['history'])->first(fn ($h) => $h[0] === $keys[$i]);
        @endphp
        <li class="step {{ $cls }}">
            <div class="dot">{{ $cls === 'done' ? '✓' : ($cls === 'ret' ? '!' : $n) }}</div>
            <b>{{ $label }}</b>
            <span>{{ $h[2] ?? ($cls === 'ret' ? 'Returned' : ($cls === 'cur' ? 'Waiting' : '—')) }}</span>
        </li>
    @endforeach
</ol>
