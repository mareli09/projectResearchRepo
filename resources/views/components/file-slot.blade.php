{{-- One typed file slot (CLAUDE.md §7 M2, §15 "file slot"). --}}
@props(['name', 'def', 'file' => null, 'older' => [], 'record', 'na' => false, 'editable' => true, 'maxMb'])
@php
    $error = $errors->first('file_'.$name);
    $accept = implode(',', array_map(fn ($e) => '.'.$e, $def['mimes']));
@endphp
<div class="border border-line rounded-md p-3 {{ $error ? 'border-bad' : '' }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <div class="font-medium">{{ $def['label'] }}</div>
            <div class="text-[13px] text-muted">{{ strtoupper(implode(' / ', $def['mimes'])) }} · up to {{ $maxMb }} MB</div>
        </div>
        @if ($na)
            <x-pill>Not applicable</x-pill>
        @elseif ($file)
            <x-pill variant="ok">Uploaded</x-pill>
        @else
            <x-pill variant="warn">Missing</x-pill>
        @endif
    </div>

    @if ($file)
        <div class="mt-2 text-[13px] flex flex-wrap gap-x-4 gap-y-1">
            <a href="{{ \App\Http\Controllers\RecordFileController::signedUrl($file) }}">{{ $file->original_name }}</a>
            <span class="text-muted">{{ number_format($file->size / 1024, 0) }} KB</span>
            <span class="mono text-muted" title="SHA-256">{{ substr($file->sha256, 0, 12) }}…</span>
        </div>
    @endif
    @if (count($older))
        <div class="mt-1 text-[13px] text-muted">{{ count($older) }} older version{{ count($older) === 1 ? '' : 's' }} kept.</div>
    @endif

    @if ($editable && ! $na)
        <form method="POST" action="{{ route('urdc.records.upload', [$record, $name]) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
            @csrf
            <label class="sr-only" for="file-{{ $name }}">{{ $def['label'] }}</label>
            <input id="file-{{ $name }}" type="file" name="file" accept="{{ $accept }}" required class="text-[13px] max-w-full">
            <x-button type="submit" size="sm">{{ $file ? 'Upload new version' : 'Upload' }}</x-button>
        </form>
    @endif
    @if ($error)<p class="field-error" role="alert">{{ $error }}</p>@endif
</div>
