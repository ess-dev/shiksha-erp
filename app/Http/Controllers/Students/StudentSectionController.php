<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Actions\ChangeSection;
use App\Domain\Students\Actions\SetRollNumber;
use App\Domain\Students\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ChangeSectionRequest;
use App\Http\Requests\Students\SetRollNumberRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StudentSectionController extends Controller
{
    public function store(ChangeSectionRequest $request, Student $student, ChangeSection $changeSection): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $changeSection->handle(
            $identity,
            $student,
            $request->string('section')->toString(),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section changed.')]);

        return back();
    }

    public function updateRoll(SetRollNumberRequest $request, Student $student, SetRollNumber $setRollNumber): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $setRollNumber->handle($identity, $student, $request->integer('roll_number'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roll number saved.')]);

        return back();
    }
}
