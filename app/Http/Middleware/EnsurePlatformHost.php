<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin routes exist only on the platform host. Anywhere else, including
 * every school host, they look like any other missing page.
 *
 * This is a host check rather than a route domain constraint so the generated
 * frontend route functions stay path-only and work on any port or domain.
 */
class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            strtolower($request->getHost()) === strtolower((string) config('shiksha.platform_host')),
            404,
        );

        return $next($request);
    }
}
