@props(['title' => null])
{{-- Public layout: landing page, catalog and study abstract for guests (CLAUDE.md §8, §15). --}}
@php $c = \App\Domain\Site\SiteContent::published(); @endphp
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $c['site']['name'] }}</title>
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <nav class="ls-nav" aria-label="Site" style="padding-top: env(safe-area-inset-top)">
        <div class="ls-in">
            <a class="ls-logo" href="{{ route('home') }}"><span class="seal">TUA</span><span>{{ $c['site']['name'] }}<small>{{ $c['site']['tagline'] }}</small></span></a>
            <div class="ls-links">{{ $links ?? '' }}</div>
            <div class="ls-cta" @if (! isset($links)) style="margin-left:auto" @endif>
                @if (config('tua.features.theme_picker'))
                    <button type="button" class="theme-btn" data-theme-toggle aria-haspopup="dialog" style="color:var(--ink);border-color:var(--line)"><i aria-hidden="true"></i>Theme</button>
                @endif
                @auth
                    <x-button :href="route('dashboard')">My dashboard</x-button>
                @else
                    <x-button :href="route('login')">Sign in</x-button>
                @endauth
                <x-button :href="route('catalog')" variant="primary">{{ $c['hero']['cta1'] }}</x-button>
            </div>
        </div>
    </nav>
    <main id="main">{{ $slot }}</main>
    <footer class="ls-foot">
        <div class="ls-in">
            <span>{{ $c['footer']['text'] }}</span>
            <span><a href="{{ route('register') }}#privacy">Privacy Notice</a> · <a href="{{ route('login') }}">Sign in</a></span>
        </div>
    </footer>
</body>
</html>
