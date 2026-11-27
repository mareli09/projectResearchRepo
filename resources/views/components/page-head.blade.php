@props(['eyebrow' => null, 'title', 'lead' => null])
<div class="head">
    <div>
        @if ($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
        <h1>{{ $title }}</h1>
        @if ($lead)<p class="text-muted small mt-1 max-w-3xl">{{ $lead }}</p>@endif
    </div>
    @isset($actions)<div class="actions">{{ $actions }}</div>@endisset
</div>
