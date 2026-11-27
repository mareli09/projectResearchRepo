@props(['score'])
@php
    $dup = (float) config('tua.screening.duplicate_threshold');
    $rev = (float) config('tua.screening.review_threshold');
    $col = $score >= $dup ? 'var(--bad)' : ($score >= $rev ? 'var(--warn)' : 'var(--ok)');
@endphp
<div class="score" title="Cosine similarity {{ number_format($score, 2) }}">
    <span class="mono">{{ number_format($score, 2) }}</span>
    <div class="bar" aria-hidden="true"><i style="width: {{ min(100, $score * 100) }}%; background: {{ $col }}"></i></div>
</div>
