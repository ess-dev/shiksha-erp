<?php

namespace App\Http\Controllers\Exams;

use App\Domain\Exams\Actions\UpdateGradeScale;
use App\Domain\Exams\Queries\GradeScaleScreenQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\UpdateGradeScaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GradeScaleController extends Controller
{
    public function show(GradeScaleScreenQuery $screen): Response
    {
        Gate::authorize('settings.manage');

        return Inertia::render('exams/grading', ['screen' => $screen->get()]);
    }

    public function update(UpdateGradeScaleRequest $request, UpdateGradeScale $updateGradeScale): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updateGradeScale->handle($identity, $request->grades());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grading scale saved.')]);

        return back();
    }
}
