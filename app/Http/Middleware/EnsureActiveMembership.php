<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks on every request that a signed-in person still has an active
 * membership of this school (and an active identity), and works out whether
 * they are acting as staff or as a parent. Revoking a membership therefore
 * signs the person out of that school on their next request. In the parent
 * context only the parent screens, sign-out and the context switch are open.
 */
class EnsureActiveMembership
{
    /** Routes a parent may reach besides the parent screens. */
    private const PARENT_CONTEXT_ROUTES = ['parent.*', 'logout', 'school.context.*'];

    public function __construct(private readonly SchoolContext $contexts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $request->user();
        $tenant = Tenant::current();

        if (! $identity instanceof Identity || ! $tenant instanceof Tenant) {
            return $next($request);
        }

        $available = $this->contexts->available($identity, $tenant);
        $context = $this->contexts->choose($request, $available);

        if ($context === null) {
            $wasParent = $request->session()->get(SchoolContext::SESSION_KEY) === SchoolContext::GUARDIAN;

            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($wasParent ? 'parent.sign-in' : 'login');
        }

        $request->session()->put(SchoolContext::SESSION_KEY, $context);
        $request->attributes->set(SchoolContext::REQUEST_ATTRIBUTE, $context);

        if ($context === SchoolContext::GUARDIAN && ! $request->routeIs(...self::PARENT_CONTEXT_ROUTES)) {
            return redirect()->route('parent.home');
        }

        return $next($request);
    }
}
