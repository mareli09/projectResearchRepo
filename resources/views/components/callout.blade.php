{{-- variant: info | ok | bad | gold --}}
@props(['variant' => 'info'])
<div {{ $attributes->merge(['class' => 'callout'.($variant !== 'info' ? ' callout-'.$variant : ''), 'role' => 'note']) }}>{{ $slot }}</div>
