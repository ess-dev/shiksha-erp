<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSectionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SectionController extends Controller
{
    public function store(StoreSectionRequest $request, CreateSection $createSection): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $campus = $request->campus();
        $schoolClass = $request->schoolClass();

        $section = $createSection->handle(
            $identity,
            $request->academicYear(),
            $campus,
            $schoolClass,
            $request->string('name')->toString(),
            $request->filled('capacity') ? $request->integer('capacity') : null,
            $request->filled('room') ? $request->string('room')->toString() : null,
            $request->filled('class_teacher') ? $request->string('class_teacher')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':class-:section added on :campus.', [
            'class' => $schoolClass->name,
            'section' => $section->name,
            'campus' => $campus->name,
        ])]);

        return back();
    }
}
