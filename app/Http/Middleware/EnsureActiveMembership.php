<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Authentication\StaffAuthenticator;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks on every request that a signed-in person still has an active
 * membership of this school (and an active identity). Revoking a membership
 * therefore signs the person out of that school on their next request.
 */
class EnsureActiveMembership
{
    public function __construct(private readonly StaffAuthenticator $staff) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $request->user();
        $tenant = Tenant::current();

        if (! $identity instanceof Identity || ! $tenant instanceof Tenant) {
            return $next($request);
        }

        if ($identity->isActive() && $this->staff->hasActiveStaffMembership($identity, $tenant)) {
            return $next($request);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
