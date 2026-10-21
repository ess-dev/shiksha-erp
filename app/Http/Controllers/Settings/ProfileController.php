<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Identity\Actions\UpdateIdentityContact;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the signed-in person's contact details.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/profile');
    }

    /**
     * Update the signed-in person's phone and email.
     */
    public function update(ProfileUpdateRequest $request, UpdateIdentityContact $updateContact): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updateContact->handle(
            $identity,
            $request->filled('email') ? $request->string('email')->toString() : null,
            $request->filled('phone') ? $request->string('phone')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
