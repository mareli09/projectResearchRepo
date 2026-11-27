{{-- Installable web app (CLAUDE.md §16): manifest, icons and the browser bar color. --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ $themeColor ?? (\App\Domain\Site\SiteContent::published()['theme']['color'] ?? config('tua.theme.default')) }}">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TUA Repository">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
