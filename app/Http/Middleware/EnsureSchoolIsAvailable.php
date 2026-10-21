<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every school request needs a resolved school whose status allows access.
 * The page for an unknown host is neutral and never says which schools exist.
 */
class EnsureSchoolIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            return $this->page($request, 'not_found', 404);
        }

        if (! $tenant->isAccessible()) {
            return $this->page($request, 'unavailable', 403);
        }

        return $next($request);
    }

    private function page(Request $request, string $key, int $status): Response
    {
        return Inertia::render('errors/school', [
            'title' => __("school.{$key}.title"),
            'message' => __("school.{$key}.message"),
        ])->toResponse($request)->setStatusCode($status);
    }
}
