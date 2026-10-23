<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Remembers which academic session the parent is looking at. A session the
     * school does not have is ignored and the current one is shown.
     */
    public function update(Request $request, AcademicSessionsQuery $sessions): RedirectResponse
    {
        $ulid = $request->validate(['session' => ['required', 'string', 'max:26']])['session'];

        if ($sessions->find($ulid) === null) {
            $request->session()->forget('parent.session');
        } else {
            $request->session()->put('parent.session', $ulid);
        }

        return redirect()->route('parent.home');
    }
}
