{{-- Marks a design screen whose actions are not built yet. --}}
@props(['sprint', 'what' => 'Buttons on this page'])
<div {{ $attributes->merge(['class' => 'preview-note']) }} role="note">
    <span class="pill pill-gold">Design preview</span>
    <span>{{ $what }} show what will happen but do not save anything yet. Built in Sprint {{ $sprint }}. Sample data is marked "sample".</span>
</div>
