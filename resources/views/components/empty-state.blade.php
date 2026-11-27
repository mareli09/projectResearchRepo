@props(['title', 'action' => null])
<div {{ $attributes->merge(['class' => 'panel text-center py-10']) }}>
    <h2>{{ $title }}</h2>
    <p class="text-muted mt-1">{{ $slot }}</p>
    @if ($action)<div class="mt-4">{{ $action }}</div>@endif
</div>
