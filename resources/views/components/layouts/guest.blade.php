@props(['title' => null, 'wide' => false])
{{-- Signed-out pages: sign in, create account, activation, recovery (CLAUDE.md §15). --}}
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.pwa-head')
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    <header class="bg-bar text-bar-ink" style="padding-top: env(safe-area-inset-top)">
        <div class="mx-auto max-w-[1180px] px-4 py-2.5 flex items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 leading-tight text-bar-ink no-underline">
                <span class="seal">TUA</span>
                <span><span class="block font-semibold">TUA Research Repository</span>
                <span class="block text-xs opacity-80">University Research and Development Center</span></span>
            </a>
            @if (config('tua.features.theme_picker'))
                <button type="button" class="theme-btn" data-theme-toggle aria-haspopup="dialog"><i aria-hidden="true"></i>Theme</button>
            @endif
        </div>
    </header>
    <main class="mx-auto px-4 py-8 {{ $wide ? 'max-w-2xl' : 'max-w-md' }}">
        <x-flash />
        {{ $slot }}
    </main>
    {{-- Livewire bundles Alpine.js, used by the register form's college → program list. --}}
    @livewireScripts
</body>
</html>
