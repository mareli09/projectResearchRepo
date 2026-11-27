{{-- Side drawer on <dialog>: open with data-drawer-open="id", close with data-drawer-close. --}}
@props(['id', 'title'])
<dialog id="{{ $id }}" class="drawer" aria-labelledby="{{ $id }}-title">
    <div class="ph mb-3"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="btn btn-sm" data-drawer-close>Close</button></div>
    {{ $slot }}
</dialog>
