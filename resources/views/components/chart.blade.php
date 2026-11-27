{{-- ECharts chart. $option is an ECharts option array; "var(--token)" strings follow the theme. $link opens the records behind a segment. --}}
@props(['option', 'link' => null, 'size' => null, 'label'])
<div {{ $attributes->merge(['class' => 'chart'.($size ? ' '.$size : '')]) }} role="img" aria-label="{{ $label }}"
     data-chart="{{ json_encode($option) }}" @if ($link) data-chart-link="{{ $link }}" @endif></div>
