<?php

namespace App\Http\Controllers\Homework;

use App\Domain\Homework\Actions\CreateHomework;
use App\Domain\Homework\Actions\DeleteHomework;
use App\Domain\Homework\Actions\MarkHomeworkChecked;
use App\Domain\Homework\Actions\SaveHomeworkFeedback;
use App\Domain\Homework\Actions\UpdateHomework;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Homework\Queries\HomeworkScreenQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Homework\MarkHomeworkCheckedRequest;
use App\Http\Requests\Homework\SaveHomeworkFeedbackRequest;
use App\Http\Requests\Homework\StoreHomeworkRequest;
use App\Http\Requests\Homework\UpdateHomeworkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeworkController extends Controller
{
    /**
     * The teacher's Homework screen (`?section=<ulid>` filters the list,
     * `?assignment=<ulid>` picks the one shown on the right). People who may
     * only view get the same screen without the New assignment button.
     */
    public function index(Request $request, HomeworkAccess $access, HomeworkScreenQuery $screen): Response
    {
        $actor = $this->actor($request);
        abort_unless($access->mayOpen($actor), 403);

        $query = fn (string $key): ?string => is_string($request->query($key)) && $request->query($key) !== '' ? $request->query($key) : null;

        return Inertia::render('homework/index', [
            'screen' => $screen->get($actor, $query('section'), $query('assignment')),
        ]);
    }

    public function store(StoreHomeworkRequest $request, CreateHomework $create): RedirectResponse
    {
        $assignment = $create->handle(
            $this->actor($request),
            $request->string('section')->toString(),
            $request->string('subject')->toString(),
            $request->string('title')->toString(),
            $request->filled('instructions') ? $request->string('instructions')->toString() : null,
            $request->string('due_on')->toString(),
            SubmissionType::from($request->string('submission_type')->toString()),
            $request->attachments(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment created; families will be notified.')]);

        return to_route('homework.index', ['assignment' => $assignment->ulid]);
    }

    public function update(UpdateHomeworkRequest $request, string $assignment, UpdateHomework $update): RedirectResponse
    {
        $update->handle(
            $this->actor($request),
            $assignment,
            $request->string('title')->toString(),
            $request->filled('instructions') ? $request->string('instructions')->toString() : null,
            $request->string('due_on')->toString(),
            SubmissionType::from($request->string('submission_type')->toString()),
            $request->attachments(),
            $request->removedFiles(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment updated.')]);

        return back();
    }

    public function destroy(Request $request, string $assignment, DeleteHomework $delete): RedirectResponse
    {
        $delete->handle($this->actor($request), $assignment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return to_route('homework.index');
    }

    public function checked(MarkHomeworkCheckedRequest $request, string $assignment, MarkHomeworkChecked $mark): RedirectResponse
    {
        $checked = $request->boolean('checked');
        $count = $mark->handle($this->actor($request), $assignment, $request->students(), $checked);

        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 0
            ? __('Nothing to change.')
            : trans_choice($checked ? '{1} 1 student marked checked.|[2,*] :count students marked checked.' : '{1} 1 student unmarked.|[2,*] :count students unmarked.', $count)]);

        return back();
    }

    public function feedback(SaveHomeworkFeedbackRequest $request, string $assignment, SaveHomeworkFeedback $save): RedirectResponse
    {
        $count = $save->handle($this->actor($request), $assignment, $request->feedback());

        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 0 ? __('No feedback changed.') : __('Feedback saved; parents can see it.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
