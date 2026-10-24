<?php

namespace App\Http\Controllers\Exams;

use App\Domain\Exams\Actions\UpdateGradeScale;
use App\Domain\Exams\Actions\UpdateReportCardApproval;
use App\Domain\Exams\Queries\GradeScaleScreenQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\UpdateGradeScaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function approval(Request $request, UpdateReportCardApproval $update): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $request->validate(['required' => ['required', 'boolean']]);

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $update->handle($identity, $request->boolean('required'));

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('required')
            ? __('Report cards now need approval before parents see them.')
            : __('Report cards now go to parents as soon as the class teacher sends them.')]);

        return back();
    }
}
