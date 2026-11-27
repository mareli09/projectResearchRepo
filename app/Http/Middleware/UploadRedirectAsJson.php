<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploads with progress (CLAUDE.md §16) are posted by script. A script would follow a redirect by
 * itself and use up the flashed message and form errors, so for those requests the redirect is
 * answered as {"redirect": url} and the browser then opens that page normally.
 */
class UploadRedirectAsJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->get('X-Tua-Upload') === '1' && $response instanceof RedirectResponse) {
            return new JsonResponse(['redirect' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
