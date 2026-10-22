<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Authentication\PlatformTwoStepSession;
use App\Domain\Platform\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-step is always required for Super Admin. Until it is passed in this
 * session the person is sent to the challenge, or to setup if they have not
 * turned it on yet.
 */
class EnsurePlatformTwoStep
{
    public function __construct(private readonly PlatformTwoStepSession $twoStep) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('platform')->user();

        if (! $user instanceof PlatformUser) {
            return redirect()->route('platform.sign-in');
        }

        if ($this->twoStep->hasPassed($user)) {
            return $next($request);
        }

        return redirect()->route(
            $user->hasEnabledTwoFactorAuthentication() ? 'platform.two-step.challenge' : 'platform.two-step.setup',
        );
    }
}
