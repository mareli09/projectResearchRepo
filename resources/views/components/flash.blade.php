@if (session('status'))
    <x-callout variant="ok" class="mb-4" role="status">{{ session('status') }}</x-callout>
@endif
