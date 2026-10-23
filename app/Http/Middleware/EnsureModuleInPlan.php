<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('module:fees'). Runs after the school is resolved.
 */
class EnsureModuleInPlan
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = Tenant::current();

        abort_unless($tenant instanceof Tenant && $tenant->hasModule($module), 404);

        return $next($request);
    }
}
