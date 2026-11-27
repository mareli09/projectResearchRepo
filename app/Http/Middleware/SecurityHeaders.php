<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers (CLAUDE.md §14 Security): HSTS on HTTPS, a Content Security Policy, and the
 * usual hardening headers. Scripts come only from this site; the one inline script (the theme,
 * applied before first paint) carries a per-request nonce. 'unsafe-eval' is needed by Alpine.js,
 * which evaluates the x-data / @click expressions in the templates. Styles allow inline style
 * attributes, used by charts and progress bars.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $self = "'self'";
        $dev = $this->viteDevServer();
        $csp = [
            'default-src' => [$self],
            'script-src' => [$self, "'nonce-{$nonce}'", "'unsafe-eval'", ...$dev],
            'style-src' => [$self, "'unsafe-inline'", ...$dev],
            'img-src' => [$self, 'data:', 'blob:'],
            'font-src' => [$self, 'data:', ...$dev],
            'connect-src' => [$self, ...$dev, ...array_map(fn ($u) => preg_replace('#^http#', 'ws', $u), $dev)],
            'manifest-src' => [$self],
            'worker-src' => [$self],
            'frame-src' => [$self],
            'frame-ancestors' => [$self],
            'form-action' => [$self],
            'base-uri' => [$self],
            'object-src' => ["'none'"],
        ];
        $policy = implode('; ', array_map(fn ($k, $v) => $k.' '.implode(' ', array_unique($v)), array_keys($csp), $csp));
        if ($request->isSecure()) {
            $policy .= '; upgrade-insecure-requests';
        }

        $headers = [
            'Content-Security-Policy' => $policy,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            // One year, subdomains included (the tua.edu.ph subdomain is HTTPS only, §19).
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        $response->headers->remove('X-Powered-By');
        // PHP adds its own X-Powered-By at the SAPI level; on the server also set expose_php = Off.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    /** @return list<string> the Vite dev server while `npm run dev` runs (local only) */
    private function viteDevServer(): array
    {
        $hot = public_path('hot');
        if (app()->isProduction() || ! is_file($hot)) {
            return [];
        }
        $url = rtrim(trim((string) file_get_contents($hot)), '/');

        return $url === '' ? [] : [$url];
    }
}
