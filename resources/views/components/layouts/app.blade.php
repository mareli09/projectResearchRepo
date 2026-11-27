@props(['title' => null])
@php
    /** @var \App\Models\User|null $me */
    $me = auth()->user();
    $nav = $me ? \App\Support\Navigation::for($me) : [];
    $roleKey = \App\Support\Navigation::roleKey($me);
    $unread = $me ? \App\Support\Navigation::unread($me) : 0;
    $initials = $me ? collect(explode(' ', preg_replace('/^(Dr|Prof|Engr)\.\s+/', '', $me->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') : '';
    $roleLabel = $me ? \App\Http\Controllers\Admin\UserController::roleLabel($me->getRoleNames()->first()) : '';
@endphp
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
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:z-50 btn">Skip to content</a>

    {{-- Full-width dashboard shell: top bar across the page, full-height sidebar, fluid content (CLAUDE.md §15, §16). --}}
    <div class="shell">
        <header class="tb">
            <a class="brand" href="{{ $me ? route('dashboard') : route('home') }}">
                <span class="seal">TUA</span>
                <span class="name">TUA Research Repository<small>University Research and Development Center</small></span>
            </a>
            <div class="tools">
                @if ($me && Route::has('search'))
                    <form class="find" method="GET" action="{{ route('search') }}" role="search">
                        <x-icon name="search" />
                        <label for="tb-q" class="sr-only">Search the repository</label>
                        <input id="tb-q" type="search" name="q" placeholder="Search studies…" value="{{ request()->routeIs('search') ? request('q') : '' }}">
                    </form>
                @endif
                <span class="ml-auto"></span>
                @if (config('tua.features.theme_picker'))
                    <button type="button" class="icon-btn hide-xs" data-theme-toggle aria-haspopup="dialog" aria-label="Theme color and appearance"><x-icon name="palette" size="20" /></button>
                @endif
                @if ($me)
                    <a class="icon-btn" href="{{ route('notifications') }}" aria-label="Notifications{{ $unread ? ', '.$unread.' unread' : '' }}">
                        <x-icon name="bell" size="20" />@if ($unread)<span class="dot">{{ $unread }}</span>@endif
                    </a>
                    <span class="sep" aria-hidden="true"></span>
                    <details class="usermenu">
                        <summary aria-label="Account menu">
                            <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                            <span class="who"><b>{{ $me->name }}</b><span>{{ $roleLabel }}</span></span>
                        </summary>
                        <div class="menu">
                            <a href="{{ route('account') }}"><x-icon name="user" />Account and recovery</a>
                            @if (config('tua.features.theme_picker'))
                                {{-- On phones the top-bar Theme button is hidden to save room; this keeps it reachable (§16). --}}
                                <button type="button" data-theme-toggle aria-haspopup="dialog"><x-icon name="palette" />Theme and appearance</button>
                            @endif
                            <a href="{{ route('home') }}"><x-icon name="globe" />Public home page</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" />Sign out</button></form>
                        </div>
                    </details>
                @else
                    <x-button :href="route('login')" size="sm" variant="primary">Sign in</x-button>
                @endif
            </div>
        </header>

        @if ($me)
            <nav class="sb" aria-label="Main">
                <div class="me">
                    <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                    <span><b>{{ $me->name }}</b><span>{{ $roleLabel }}{{ $me->academicUnit ? ' · '.$me->academicUnit->code : '' }}</span></span>
                </div>
                @foreach ($nav as $group => $items)
                    <div class="grp">{{ $group }}</div>
                    @foreach ($items as [$label, $route, $badge])
                        @php $current = request()->routeIs($route) || request()->routeIs($route.'.*'); @endphp
                        <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif>
                            <x-icon :name="\App\Support\Navigation::ICONS[$route] ?? 'folder'" />
                            <span class="lbl">{{ $label }}</span>
                            @if ($badge)<span class="cnt" aria-label="{{ $badge }} waiting">{{ $badge }}</span>@endif
                        </a>
                    @endforeach
                @endforeach
            </nav>
        @endif

        <main id="main" class="app-main" @unless ($me) style="grid-column: 1 / -1" @endunless>
            <div class="page">
                <x-flash />
                {{ $slot }}
            </div>
            <footer class="app-foot"><span>© {{ date('Y') }} Trinity University of Asia · URDC</span><span>Times in Asia/Manila</span></footer>
        </main>
    </div>
    @livewireScripts
</body>
</html>
