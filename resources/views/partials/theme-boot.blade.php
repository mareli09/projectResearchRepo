{{--
  Theme color and appearance (CLAUDE.md §9), applied before first paint so the page never flashes.
  Site default and "let users pick" come from the landing page settings; the user's own choice is
  kept in localStorage (guests) — saving it to users.theme_color / theme_mode comes with Sprint 8.
--}}
@php
    $themeDefault = \App\Domain\Site\SiteContent::published()['theme']['color'] ?? config('tua.theme.default');
    $themeAllowUser = (bool) (\App\Domain\Site\SiteContent::published()['theme']['allowUser'] ?? true) && config('tua.features.theme_picker');
@endphp
<script>
(function () {
    var D = document.documentElement;
    D.dataset.defaultColor = @json($themeDefault);
    D.dataset.allowUser = @json($themeAllowUser ? '1' : '0');

    function hex2hsl(h) {
        h = h.replace('#', ''); if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
        var r = parseInt(h.slice(0, 2), 16) / 255, g = parseInt(h.slice(2, 4), 16) / 255, b = parseInt(h.slice(4, 6), 16) / 255;
        var mx = Math.max(r, g, b), mn = Math.min(r, g, b), H = 0, S = 0, L = (mx + mn) / 2;
        if (mx !== mn) { var d = mx - mn; S = L > .5 ? d / (2 - mx - mn) : d / (mx + mn); H = mx === r ? (g - b) / d + (g < b ? 6 : 0) : mx === g ? (b - r) / d + 2 : (r - g) / d + 4; H *= 60; }
        return [H, S, L];
    }
    function hsl(h, s, l) { return 'hsl(' + h.toFixed(0) + ' ' + (s * 100).toFixed(0) + '% ' + (l * 100).toFixed(0) + '%)'; }

    window.tuaTheme = {
        get: function () { try { return JSON.parse(localStorage.getItem('tua-theme-user')) || {}; } catch (e) { return {}; } },
        set: function (t) { try { localStorage.setItem('tua-theme-user', JSON.stringify(t)); } catch (e) {} window.tuaTheme.apply(); },
        color: function () { var t = window.tuaTheme.get(); return (D.dataset.allowUser === '1' && t.color) || D.dataset.defaultColor; },
        apply: function () {
            var hs = hex2hsl(window.tuaTheme.color()), h = hs[0], s = Math.min(hs[1], .75);
            // Light: lightness capped at 36% so white text on the accent passes WCAG AA.
            var A = hsl(h, s, Math.min(hs[2], .36));
            var light = '--accent:' + A + ';--accent-soft:' + hsl(h, Math.min(s, .55), .93) + ';--bar:' + A + ';--c1:' + A + ';--heat1:' + A + ';';
            var dA = hsl(h, Math.min(s + .1, .7), .74);
            var dark = '--accent:' + dA + ';--accent-soft:' + hsl(h, Math.min(s, .45), .2) + ';--bar:' + hsl(h, Math.min(s, .5), .14) + ';--c1:' + dA + ';--heat1:' + dA + ';';
            var el = document.getElementById('theme-style');
            if (!el) { el = document.createElement('style'); el.id = 'theme-style'; document.head.appendChild(el); }
            el.textContent = ':root{' + light + '}:root[data-theme="dark"]{' + dark + '}';
            var mode = window.tuaTheme.get().mode || 'system';
            if (mode === 'system') mode = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if (D.dataset.theme !== mode) D.dataset.theme = mode;
        }
    };
    window.tuaTheme.apply();
})();
</script>
