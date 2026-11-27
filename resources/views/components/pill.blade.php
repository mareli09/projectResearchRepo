{{-- variant: ok | warn | bad | info | gold | (none) --}}
@props(['variant' => null])
<span {{ $attributes->merge(['class' => 'pill'.($variant ? ' pill-'.$variant : '')]) }}>{{ $slot }}</span>
