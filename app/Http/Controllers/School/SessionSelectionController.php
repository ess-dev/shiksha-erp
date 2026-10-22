<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Students\StudentSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\SelectSessionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SessionSelectionController extends Controller
{
    /**
     * Remembers the academic session chosen in the top bar for this browser
     * session (empty means the current one). Only people holding
     * `students.view_past_sessions` may pick another session.
     */
    public function update(SelectSessionRequest $request, StudentSession $sessions, AcademicSessionsQuery $years): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $ulid = $request->input('session');

        if (! is_string($ulid) || $ulid === '') {
            $request->session()->forget('school.session');

            return back();
        }

        if (! $sessions->canChoose($identity) || $years->find($ulid) === null) {
            throw ValidationException::withMessages(['session' => __('That session is not available to you.')]);
        }

        $request->session()->put('school.session', $ulid);

        return back();
    }
}
