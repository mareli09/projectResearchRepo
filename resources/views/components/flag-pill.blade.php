{{-- Similarity flag (CLAUDE.md §7 M5): at or above the duplicate threshold, at or above the review threshold, or original. --}}
@props(['score'])
@php
    $dup = (float) config('tua.screening.duplicate_threshold');
    $rev = (float) config('tua.screening.review_threshold');
    [$v, $t] = $score >= $dup ? ['bad', 'Probable duplicate'] : ($score >= $rev ? ['warn', 'Similar, needs review'] : ['ok', 'Original']);
@endphp
<x-pill :variant="$v">{{ $t }}</x-pill>
