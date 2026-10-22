<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\SignInPlatformUser;
use App\Domain\Platform\Actions\SignOutPlatformUser;
use App\Domain\Platform\Exceptions\PlatformSignInFailed;
use App\Domain\Platform\Models\PlatformUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SignInRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SignInController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user('platform') instanceof PlatformUser) {
            return redirect()->route('platform.dashboard');
        }

        return Inertia::render('platform/auth/sign-in');
    }

    public function store(SignInRequest $request, SignInPlatformUser $signIn): RedirectResponse
    {
        try {
            $signIn->handle($request->string('email')->toString(), $request->string('password')->toString());
        } catch (PlatformSignInFailed $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return redirect()->route('platform.dashboard');
    }

    public function destroy(Request $request, SignOutPlatformUser $signOut): RedirectResponse
    {
        $user = $request->user('platform');

        if ($user instanceof PlatformUser) {
            $signOut->handle($user);
        }

        return redirect()->route('platform.sign-in');
    }
}
