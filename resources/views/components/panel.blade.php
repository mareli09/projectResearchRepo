@props(['title' => null])
<section {{ $attributes->merge(['class' => 'panel']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-3 mb-3">
            @if ($title)<h2>{{ $title }}</h2>@endif
            {{ $actions ?? '' }}
        </div>
    @endif
    {{ $slot }}
</section>
