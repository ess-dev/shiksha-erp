<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Actions\RequestGuardianOtp;
use App\Domain\Identity\Actions\VerifyGuardianOtp;
use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Exceptions\OtpThrottled;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\PhoneNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\SendCodeRequest;
use App\Http\Requests\Guardian\VerifyCodeRequest;
use App\Tenancy\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Parent sign-in on the school's own host: phone, then a one-time code. The
 * answer never says whether the phone belongs to a parent.
 */
class SignInController extends Controller
{
    private const PHONE_KEY = 'parent.sign_in_phone';

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user() instanceof Identity) {
            return redirect()->route(SchoolContext::current($request) === SchoolContext::GUARDIAN ? 'parent.home' : 'dashboard');
        }

        return Inertia::render('parent/auth/sign-in', [
            'school_name' => $this->tenant()->name,
        ]);
    }

    public function sendCode(SendCodeRequest $request, RequestGuardianOtp $otp): RedirectResponse
    {
        $phone = (string) $request->validated('phone');

        try {
            $otp->handle($this->tenant(), $phone, (string) $request->ip());
        } catch (OtpThrottled $e) {
            return back()->withErrors(['phone' => 'Too many tries. Please wait '.max(1, (int) ceil($e->retryAfterSeconds / 60)).' minutes and try again.']);
        }

        $request->session()->put(self::PHONE_KEY, $phone);

        return redirect()->route('parent.verify');
    }

    public function resend(Request $request, RequestGuardianOtp $otp): RedirectResponse
    {
        $phone = $request->session()->get(self::PHONE_KEY);

        if (! is_string($phone)) {
            return redirect()->route('parent.sign-in');
        }

        try {
            $otp->handle($this->tenant(), $phone, (string) $request->ip());
        } catch (OtpThrottled $e) {
            return back()->withErrors(['code' => 'Too many tries. Please wait '.max(1, (int) ceil($e->retryAfterSeconds / 60)).' minutes and try again.']);
        }

        return back()->with('status', 'If this number is registered, a new code is on its way.');
    }

    public function showVerify(Request $request): Response|RedirectResponse
    {
        $phone = $request->session()->get(self::PHONE_KEY);

        if (! is_string($phone)) {
            return redirect()->route('parent.sign-in');
        }

        $parsed = PhoneNumber::tryParse($phone);

        return Inertia::render('parent/auth/verify', [
            'school_name' => $this->tenant()->name,
            'phone_masked' => $parsed === null ? '' : $parsed->masked(),
            'code_length' => (int) config('shiksha.otp.length'),
            'resend_seconds' => (int) config('shiksha.otp.resend_seconds'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(VerifyCodeRequest $request, VerifyGuardianOtp $verify): RedirectResponse
    {
        $phone = $request->session()->get(self::PHONE_KEY);

        if (! is_string($phone)) {
            return redirect()->route('parent.sign-in');
        }

        $identity = $verify->handle($this->tenant(), $phone, (string) $request->validated('code'));

        if (! $identity instanceof Identity) {
            return back()->withErrors(['code' => 'That code did not work. Check it, or ask for a new one.']);
        }

        Auth::guard()->setRememberDuration((int) config('shiksha.otp.remember_days') * 24 * 60);

        Auth::login($identity, true);

        $request->session()->regenerate();
        $request->session()->forget(self::PHONE_KEY);
        $request->session()->put(SchoolContext::SESSION_KEY, SchoolContext::GUARDIAN);

        return redirect()->route('parent.home');
    }

    public function signOut(Request $request): RedirectResponse
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('parent.sign-in');
    }

    private function tenant(): Tenant
    {
        return Tenant::current() ?? abort(404);
    }
}
