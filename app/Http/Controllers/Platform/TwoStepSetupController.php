<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\ConfirmTwoStepSetup;
use App\Domain\Platform\Actions\StartTwoStepSetup;
use App\Domain\Platform\Authentication\PlatformTwoStepSession;
use App\Domain\Platform\Exceptions\InvalidTwoStepCode;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Queries\TwoStepSetupQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ConfirmTwoStepRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoStepSetupController extends Controller
{
    public function show(Request $request, TwoStepSetupQuery $setup): Response|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('platform.two-step.challenge');
        }

        return Inertia::render('platform/auth/two-step-setup', [
            'setup' => $setup->get($user),
        ]);
    }

    public function begin(Request $request, StartTwoStepSetup $start): RedirectResponse
    {
        $start->handle($this->user($request));

        return redirect()->route('platform.two-step.setup');
    }

    public function confirm(ConfirmTwoStepRequest $request, ConfirmTwoStepSetup $confirm): RedirectResponse
    {
        try {
            $confirm->handle($this->user($request), $request->string('code')->toString());
        } catch (InvalidTwoStepCode $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        return redirect()->route('platform.two-step.recovery-codes');
    }

    public function recoveryCodes(Request $request, TwoStepSetupQuery $setup, PlatformTwoStepSession $session): Response|RedirectResponse
    {
        $user = $this->user($request);

        if (! $user->hasEnabledTwoFactorAuthentication() || ! $session->hasPassed($user)) {
            return redirect()->route('platform.two-step.setup');
        }

        return Inertia::render('platform/auth/recovery-codes', [
            'recoveryCodes' => $setup->recoveryCodes($user),
        ]);
    }

    private function user(Request $request): PlatformUser
    {
        $user = $request->user('platform');
        abort_unless($user instanceof PlatformUser, 403);

        return $user;
    }
}
