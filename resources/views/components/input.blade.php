{{-- Labelled input with its error written next to the field (CLAUDE.md §14 WCAG 2.2 AA). --}}
@props(['name', 'label', 'type' => 'text', 'hint' => null, 'bag' => 'default', 'errorKey' => null])
@php
    $key = $errorKey ?? $name;
    $error = $errors->getBag($bag)->first($key);
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
@endphp
<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="font-medium">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-err" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except(['value', 'id'])->merge(['class' => 'w-full min-h-11 rounded-md border bg-surface px-3 '.($error ? 'border-bad' : 'border-line')]) }}>
    @if ($hint && ! $error)<p id="{{ $id }}-hint" class="text-[13px] text-muted">{{ $hint }}</p>@endif
    @if ($error)<p id="{{ $id }}-err" class="field-error">{{ $error }}</p>@endif
</div>
