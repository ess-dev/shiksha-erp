<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Tenancy\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Switches between the staff and the parent view for someone who is both.
 * Going to the parent view is open to a signed-in staff member. Going to the
 * staff view from the parent view signs out first, because the parent code
 * proves a phone, not the staff password.
 */
class ContextController extends Controller
{
    public function update(Request $request, SchoolContext $contexts): RedirectResponse
    {
        $target = $request->validate([
            'context' => ['required', Rule::in([SchoolContext::STAFF, SchoolContext::GUARDIAN])],
        ])['context'];

        $identity = $request->user();
        $tenant = Tenant::current();

        abort_unless($identity instanceof Identity && $tenant instanceof Tenant, 403);
        abort_unless(in_array($target, $contexts->available($identity, $tenant), true), 403);

        if ($target === SchoolContext::GUARDIAN) {
            $request->session()->put(SchoolContext::SESSION_KEY, SchoolContext::GUARDIAN);

            return redirect()->route('parent.home');
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sign in with your password to open the staff area.');
    }
}
