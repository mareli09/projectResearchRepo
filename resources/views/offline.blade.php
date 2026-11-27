{{-- "You are offline" (CLAUDE.md §16). Self-contained: no scripts, no personal data, inline styles, so it works from the cache. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1b4a42">
    <title>You are offline · TUA Research Repository</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
               background: #f8f5ec; color: #0f2724; padding: max(16px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom)); }
        main { max-width: 420px; text-align: center; }
        .seal { display: inline-grid; place-items: center; width: 64px; height: 64px; border-radius: 16px; background: #1b4a42; color: #f8f5ec; font-weight: 700; letter-spacing: .02em; }
        h1 { font-family: "IBM Plex Serif", Georgia, serif; font-weight: 600; font-size: 26px; margin: 18px 0 8px; }
        p { color: #5c6b66; line-height: 1.5; margin: 0 0 18px; }
        a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 20px; border-radius: 999px;
            background: #1b4a42; color: #fff; text-decoration: none; font-weight: 600; }
        a:focus-visible { outline: 3px solid #c9f26b; outline-offset: 2px; }
        @media (prefers-color-scheme: dark) { body { background: #0b1a17; color: #e6efec; } p { color: #9fb5ae; } a { background: #8fd3bf; color: #0b1a17; } }
    </style>
</head>
<body>
<main>
    <span class="seal" aria-hidden="true">TUA</span>
    <h1>You are offline</h1>
    <p>The TUA Research Repository needs a connection. Check your Wi-Fi or mobile data, then try again. Nothing you were doing was sent.</p>
    <a href="/">Try again</a>
</main>
</body>
</html>
