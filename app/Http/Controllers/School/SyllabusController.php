<?php

namespace App\Http\Controllers\School;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\SaveClassSyllabus;
use App\Domain\School\Actions\SaveSectionProgress;
use App\Domain\School\Actions\SaveStudentSubjectNotes;
use App\Domain\School\Queries\SyllabusScreenQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\SaveClassSyllabusRequest;
use App\Http\Requests\School\SaveSectionProgressRequest;
use App\Http\Requests\School\SaveStudentSubjectNotesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusController extends Controller
{
    /**
     * The teacher's Subjects screen: `?section=<ulid>&subject=<ulid>` choose
     * what to edit; anything the person may not open falls back to their first.
     */
    public function index(Request $request, SyllabusScreenQuery $screen): Response
    {
        Gate::authorize('subjects.update');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $section = $request->query('section');
        $subject = $request->query('subject');

        return Inertia::render('school/syllabus', [
            'screen' => $screen->get($identity, is_string($section) ? $section : null, is_string($subject) ? $subject : null),
        ]);
    }

    public function updateClass(SaveClassSyllabusRequest $request, string $section, string $subject, SaveClassSyllabus $save): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $save->handle(
            $identity,
            $section,
            $subject,
            $request->filled('book') ? $request->string('book')->toString() : null,
            $request->string('chapters')->toString(),
            $request->filled('assessment') ? $request->string('assessment')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved for the whole class.')]);

        return back();
    }

    public function updateSection(SaveSectionProgressRequest $request, string $section, string $subject, SaveSectionProgress $save): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $save->handle(
            $identity,
            $section,
            $subject,
            $request->done(),
            $request->filled('current') ? $request->integer('current') : null,
            $request->filled('periods') ? $request->integer('periods') : null,
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved for this section.')]);

        return back();
    }

    public function updateStudents(SaveStudentSubjectNotesRequest $request, string $section, string $subject, SaveStudentSubjectNotes $save): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $changed = $save->handle($identity, $section, $subject, $request->notes());

        Inertia::flash('toast', $changed === 0
            ? ['type' => 'info', 'message' => __('No changes to save.')]
            : ['type' => 'success', 'message' => trans_choice('{1} :count note saved.|[2,*] :count notes saved.', $changed)]);

        return back();
    }
}
