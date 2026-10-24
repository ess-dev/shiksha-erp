<?php

namespace App\Http\Controllers\Exams;

use App\Domain\Exams\Actions\ChangeExamStatus;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ChangeExamStatusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ExamStatusController extends Controller
{
    public function update(ChangeExamStatusRequest $request, Exam $exam, ChangeExamStatus $changeStatus): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $exam = $changeStatus->handle($identity, $exam, $request->status());

        $message = match ($exam->status) {
            ExamStatus::Marking => ':name: marking is open.',
            ExamStatus::Published => ':name: results published.',
            ExamStatus::Cancelled => ':name cancelled.',
            ExamStatus::Scheduled => ':name is scheduled.',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => __($message, ['name' => $exam->name])]);

        return back();
    }
}
