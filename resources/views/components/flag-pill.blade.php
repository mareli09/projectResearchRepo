{{-- Similarity flag (CLAUDE.md §7 M5), thresholds from Settings. --}}
@props(['score'])
@php
    $flag = \App\Domain\Screening\Thresholds::flag((float) $score);
    $v = ['probable_duplicate' => 'bad', 'similar' => 'warn', 'original' => 'ok'][$flag];
@endphp
<x-pill :variant="$v">{{ \App\Domain\Screening\Thresholds::label($flag) }}</x-pill>
