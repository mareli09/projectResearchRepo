@props(['label', 'value', 'hint' => null])
<div {{ $attributes->merge(['class' => 'panel']) }}>
    <div class="eyebrow">{{ $label }}</div>
    <div class="text-2xl font-semibold mt-1 tabular-nums">{{ $value }}</div>
    @if ($hint)<div class="text-xs text-muted mt-1">{{ $hint }}</div>@endif
</div>
