@props(['score'])
@php
    $flag = \App\Domain\Screening\Thresholds::flag((float) $score);
    $col = ['probable_duplicate' => 'var(--bad)', 'similar' => 'var(--warn)', 'original' => 'var(--ok)'][$flag];
@endphp
<div class="score" title="Cosine similarity {{ number_format($score, 2) }}">
    <span class="mono">{{ number_format($score, 2) }}</span>
    <div class="bar" aria-hidden="true"><i style="width: {{ min(100, $score * 100) }}%; background: {{ $col }}"></i></div>
</div>
