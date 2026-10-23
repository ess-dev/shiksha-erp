<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Models\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\SetClassTeacherRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClassTeacherController extends Controller
{
    public function update(SetClassTeacherRequest $request, Section $section, SetClassTeacher $setClassTeacher): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $setClassTeacher->handle($identity, $section, $request->filled('staff') ? $request->string('staff')->toString() : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class teacher saved.')]);

        return back();
    }
}
