<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\Actions\ChangeStaffPhoto;
use App\Domain\Access\Actions\UpdateStaffNotificationChannels;
use App\Domain\Access\Actions\UpdateStaffProfile;
use App\Domain\Access\Queries\StaffProfileQuery;
use App\Domain\Identity\Actions\UpdateIdentityContact;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\DomainRuleResponse;
use App\Http\Requests\Settings\ProfileChannelsRequest;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class ProfileController extends Controller
{
    /**
     * Show the signed-in person's profile: their own details, what the school
     * keeps about them (read-only) and how the school reaches them.
     */
    public function edit(Request $request, StaffProfileQuery $profile): Response
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return Inertia::render('settings/profile', ['profile' => $profile->forIdentity($identity)]);
    }

    /**
     * Update the signed-in person's mobile (their sign-in phone) and the
     * details they keep about themselves.
     */
    public function update(
        ProfileUpdateRequest $request,
        UpdateStaffProfile $updateProfile,
        UpdateIdentityContact $updateContact,
        StaffProfileQuery $profile,
    ): RedirectResponse {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $phone = $request->filled('phone') ? $request->string('phone')->toString() : null;

        if ($profile->forIdentity($identity) === null) {
            $updateContact->handle($identity, $identity->email, $phone);
        } else {
            $updateProfile->handle($identity, $phone, $request->only(UpdateStaffProfile::FIELDS));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Save one or more notification channel choices.
     */
    public function channels(ProfileChannelsRequest $request, UpdateStaffNotificationChannels $update): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $update->handle($identity, $request->validated());

        return back();
    }

    /**
     * Stream the signed-in person's own photo (never a public path).
     */
    public function photo(Request $request, StaffProfileQuery $profile): BaseResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $path = $profile->photoPath($identity);
        abort_if($path === null || ! Storage::disk('tenant')->exists($path), 404);

        return Storage::disk('tenant')->response($path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storePhoto(ProfilePhotoRequest $request, ChangeStaffPhoto $changePhoto): BaseResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        try {
            $changePhoto->handle($identity, $request->file('photo'));
        } catch (DomainException $e) {
            return DomainRuleResponse::for($e, $request, 'photo');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo updated.')]);

        return back();
    }

    public function destroyPhoto(Request $request, ChangeStaffPhoto $changePhoto): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $changePhoto->handle($identity, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo removed.')]);

        return back();
    }
}
