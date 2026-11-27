{{-- KPI card: label, big value, optional hint and a soft round icon on the right. --}}
@props(['label', 'value', 'hint' => null, 'up' => false, 'tone' => null, 'icon' => null, 'iconTone' => 'accent'])
<div class="kpi {{ $icon ? 'kpi-ic' : '' }}">
    <div class="min-w-0">
        <span class="eyebrow">{{ $label }}</span>
        <span class="v block" @if ($tone) style="color: var(--{{ $tone }})" @endif>{{ $value }}</span>
        @if ($hint)<span class="d block {{ $up ? 'up' : '' }}">{{ $hint }}</span>@endif
    </div>
    @if ($icon)
        <span class="kpi-icon" style="color: var(--{{ $iconTone }}); background: var(--{{ $iconTone }}-soft)"><x-icon :name="$icon" size="24" /></span>
    @endif
</div>
