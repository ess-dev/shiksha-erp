<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\SetSectionSubjectTeacher;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\Subject;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\SetSubjectTeacherRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SubjectTeacherController extends Controller
{
    public function update(SetSubjectTeacherRequest $request, Section $section, Subject $subject, SetSectionSubjectTeacher $setTeacher): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $setTeacher->handle($identity, $section, $subject, $request->filled('staff') ? $request->string('staff')->toString() : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject teacher saved.')]);

        return back();
    }
}
