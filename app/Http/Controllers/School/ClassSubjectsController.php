<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\SetClassSubjects;
use App\Domain\School\Models\SchoolClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\SetClassSubjectsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClassSubjectsController extends Controller
{
    public function update(SetClassSubjectsRequest $request, SchoolClass $schoolClass, SetClassSubjects $setClassSubjects): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $setClassSubjects->handle($identity, $schoolClass, $request->subjects());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':class subjects saved.', ['class' => $schoolClass->name])]);

        return back();
    }
}
