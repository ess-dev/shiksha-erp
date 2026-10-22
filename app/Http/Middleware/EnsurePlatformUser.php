<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin pages need a signed-in, still-active platform user. A disabled
 * account is signed out on its next request.
 */
class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('platform')->user();

        if ($user instanceof PlatformUser && $user->isActive()) {
            return $next($request);
        }

        if ($user instanceof PlatformUser) {
            Auth::guard('platform')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('platform.sign-in');
    }
}
