<?php

namespace App\Http\Controllers\Exams;

use App\Domain\Exams\Actions\ReopenMarkSheet;
use App\Domain\Exams\Actions\SaveMarks;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Queries\MarksScreenQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ReopenMarksRequest;
use App\Http\Requests\Exams\SaveMarksRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MarksController extends Controller
{
    /**
     * Marks entry: `?exam=<ulid>&section=<ulid>&subject=<ulid>` choose the
     * sheet; anything the person cannot open falls back to their first.
     */
    public function index(Request $request, MarksScreenQuery $screen): Response
    {
        abort_unless(Gate::any(['marks.enter', 'marks.reopen']), 403);

        $query = fn (string $key): ?string => is_string($request->query($key)) ? $request->query($key) : null;

        return Inertia::render('exams/marks', [
            'screen' => $screen->get($this->actor($request), $query('exam'), $query('section'), $query('subject')),
        ]);
    }

    public function update(SaveMarksRequest $request, Exam $exam, string $section, string $subject, SaveMarks $saveMarks): RedirectResponse
    {
        $publish = $request->boolean('publish');

        $saveMarks->handle($this->actor($request), $exam, $section, $subject, $request->marks(), $publish);

        Inertia::flash('toast', $publish
            ? ['type' => 'success', 'message' => __('Published. Report cards are updated.')]
            : ['type' => 'success', 'message' => __('Draft saved. Parents can’t see it until you publish.')]);

        return back();
    }

    public function reopen(ReopenMarksRequest $request, Exam $exam, string $section, string $subject, ReopenMarkSheet $reopen): RedirectResponse
    {
        $reopen->handle($this->actor($request), $exam, $section, $subject, $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reopened. Parents no longer see these marks until they are published again.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
