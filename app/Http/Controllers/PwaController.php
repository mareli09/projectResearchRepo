<?php

namespace App\Http\Controllers;

use App\Domain\Site\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Installable web app (CLAUDE.md §16 "PWA"): the manifest, whose theme color is the site's
 * current accent, and the offline page the service worker shows when there is no connection.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $accent = (string) (SiteContent::published()['theme']['color'] ?? config('tua.theme.default'));

        return response()->json([
            'name' => 'TUA Research Repository',
            'short_name' => 'TUA Repository',
            'description' => 'Research outputs of Trinity University of Asia, from the University Research and Development Center.',
            'id' => '/',
            'start_url' => '/dashboard?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f8f5ec',
            'theme_color' => $accent,
            'lang' => 'en',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES);
    }

    /** Static and the same for everyone: it is cached by the service worker, so it holds no personal data. */
    public function offline(): Response
    {
        return response()->view('offline')->header('Cache-Control', 'public, max-age=86400');
    }
}
