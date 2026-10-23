<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Authentication\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The parent screens: a signed-in guardian only. Nobody signed in goes to the
 * parent sign-in; staff acting as staff go back to the staff area.
 */
class EnsureGuardianContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('parent.sign-in');
        }

        if (SchoolContext::current($request) !== SchoolContext::GUARDIAN) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
