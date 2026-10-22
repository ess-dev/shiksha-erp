<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\CampusOptionData;
use App\Domain\School\Queries\AccessibleCampusesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\SelectCampusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CampusSelectionController extends Controller
{
    /**
     * Remembers the campus chosen in the top bar for this session (empty means
     * all campuses). The choice must be one the person can reach: it only
     * narrows what they see and never widens it.
     */
    public function update(SelectCampusRequest $request, AccessibleCampusesQuery $campuses): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $ulid = $request->input('campus');

        if (! is_string($ulid) || $ulid === '') {
            $request->session()->forget('school.campus');

            return back();
        }

        $reachable = array_map(fn (CampusOptionData $campus) => $campus->ulid, $campuses->get($identity));

        if (! in_array($ulid, $reachable, true)) {
            throw ValidationException::withMessages(['campus' => __('That campus is not available to you.')]);
        }

        $request->session()->put('school.campus', $ulid);

        return back();
    }
}
