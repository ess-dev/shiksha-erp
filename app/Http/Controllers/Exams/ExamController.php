<?php

namespace App\Http\Controllers\Exams;

use App\Domain\Exams\Actions\CreateExam;
use App\Domain\Exams\Actions\DeleteExam;
use App\Domain\Exams\Actions\UpdateExam;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Queries\ExamsOverviewQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SaveExamRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function index(Request $request, ExamsOverviewQuery $overview): Response
    {
        Gate::authorize('exams.schedule');

        return Inertia::render('exams/index', [
            'overview' => $overview->get($this->actor($request)),
        ]);
    }

    public function store(SaveExamRequest $request, CreateExam $createExam): RedirectResponse
    {
        $exam = $createExam->handle($this->actor($request), $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created.', ['name' => $exam->name])]);

        return back();
    }

    public function update(SaveExamRequest $request, Exam $exam, UpdateExam $updateExam): RedirectResponse
    {
        $exam = $updateExam->handle($this->actor($request), $exam, $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved.', ['name' => $exam->name])]);

        return back();
    }

    public function destroy(Request $request, Exam $exam, DeleteExam $deleteExam): RedirectResponse
    {
        Gate::authorize('exams.schedule');

        $deleteExam->handle($this->actor($request), $exam);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
