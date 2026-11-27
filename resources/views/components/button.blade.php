{{--
  variant: default | primary | ok | bad | gold | ghost ; size: md | sm ; href renders a link.
  preview="Sprint 3" makes a design-only button: it never submits and shows a toast saying when it is built.
--}}
@props(['variant' => 'default', 'size' => 'md', 'href' => null, 'type' => 'button', 'preview' => null])
@php
    $classes = 'btn'.($variant !== 'default' ? ' btn-'.$variant : '').($size === 'sm' ? ' btn-sm' : '');
@endphp
@if ($preview)
    <button type="button" data-preview="{{ $preview }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
