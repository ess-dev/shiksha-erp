<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\VerifyTwoStepChallenge;
use App\Domain\Platform\Authentication\PlatformTwoStepSession;
use App\Domain\Platform\Exceptions\InvalidTwoStepCode;
use App\Domain\Platform\Models\PlatformUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\TwoStepChallengeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoStepChallengeController extends Controller
{
    public function create(Request $request, PlatformTwoStepSession $session): Response|RedirectResponse
    {
        $user = $this->user($request);

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('platform.two-step.setup');
        }

        if ($session->hasPassed($user)) {
            return redirect()->route('platform.dashboard');
        }

        return Inertia::render('platform/auth/two-step-challenge');
    }

    public function store(TwoStepChallengeRequest $request, VerifyTwoStepChallenge $verify): RedirectResponse
    {
        $user = $this->user($request);

        abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);

        try {
            $verify->handle($user, $request->input('code'), $request->input('recovery_code'));
        } catch (InvalidTwoStepCode $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return redirect()->intended(route('platform.dashboard'));
    }

    private function user(Request $request): PlatformUser
    {
        $user = $request->user('platform');
        abort_unless($user instanceof PlatformUser, 403);

        return $user;
    }
}
