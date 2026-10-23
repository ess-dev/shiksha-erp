<?php

namespace App\Http\Middleware;

use App\Domain\Access\TwoStepRequirement;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owners and holders of critical permissions cannot use the staff area until
 * two-step sign-in is set up; they are sent to the security settings, from
 * where they can enable it (and sign out).
 */
class EnsureTwoStepWhenRequired
{
    /** Paths that stay reachable so two-step can be set up. */
    private const ALLOWED = [
        'settings/security',
        'settings/password',
        'user/*',
        'two-factor-challenge',
        'logout',
        'login',
    ];

    public function __construct(private readonly TwoStepRequirement $requirement) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $request->user();

        if (! $identity instanceof Identity
            || ! Tenant::current() instanceof Tenant
            || $request->is(...self::ALLOWED)
            || ! $this->requirement->isRequiredFor($identity)
            || $this->requirement->isSatisfiedBy($identity)) {
            return $next($request);
        }

        return redirect()->route('security.edit');
    }
}
