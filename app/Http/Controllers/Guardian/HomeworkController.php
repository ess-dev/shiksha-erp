<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Homework\Actions\OpenHomeworkSubmissionFile;
use App\Domain\Homework\Actions\SubmitHomework;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkFileStore;
use App\Domain\Homework\Queries\HomeworkFileQuery;
use App\Domain\Homework\Queries\ParentHomeworkQuery;
use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\ParentPortalContext;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Http\Controllers\Concerns\StreamsHomeworkFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Homework\SubmitHomeworkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HomeworkController extends Controller
{
    use StreamsHomeworkFiles;

    public function __construct(
        private readonly ParentPortalContextQuery $portal,
        private readonly SchoolContext $contexts,
    ) {}

    /**
     * The selected child's homework for the viewed session (`?child=<ulid>`):
     * what is due, handed in, missed, with the teacher's feedback.
     */
    public function index(Request $request, ParentHomeworkQuery $homework): Response
    {
        $context = $this->context($request);

        return Inertia::render('parent/homework', [
            'shell' => $context->shell,
            'items' => $context->child === null ? [] : $homework->get($context->child, $context->session),
            'child_ulid' => $context->child?->ulid,
            'child_first_name' => $context->child?->firstName,
            'section_label' => $context->child?->sectionLabel,
            'session_name' => $context->session['name'],
            'is_current_session' => $context->session['is_current'],
            'max_files' => HomeworkFileStore::MAX_FILES,
            'max_file_mb' => (int) (HomeworkFileStore::MAX_BYTES / 1024 / 1024),
        ]);
    }

    /**
     * Uploads the child's work. Only in the current session; the child and the
     * homework are checked in the Action.
     */
    public function submit(SubmitHomeworkRequest $request, string $assignment, SubmitHomework $submit): RedirectResponse
    {
        $context = $this->context($request);
        $child = $context->child ?? throw HomeworkException::notFound();

        if (! $context->session['is_current']) {
            throw HomeworkException::archived();
        }

        $submit->handle($this->identity($request), $child, $assignment, $request->uploads());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Homework submitted.')]);

        return back();
    }

    public function attachment(Request $request, string $file, HomeworkFileQuery $files): StreamedResponse
    {
        $found = $files->attachmentForChild($this->context($request)->child ?? throw HomeworkException::notFound(), $file);

        return $this->streamHomeworkFile($found->file_path, $found->file_name, $found->mime_type);
    }

    public function submission(Request $request, string $file, HomeworkFileQuery $files, OpenHomeworkSubmissionFile $open): StreamedResponse
    {
        $found = $files->submissionFileForChild($this->context($request)->child ?? throw HomeworkException::notFound(), $file);
        $path = $open->handle($found);

        return $this->streamHomeworkFile($path, $found->file_name, $found->mime_type);
    }

    private function context(Request $request): ParentPortalContext
    {
        $identity = $this->identity($request);

        return $this->portal->resolve(
            $identity,
            $request->filled('child') ? $request->string('child')->toString() : null,
            $request->session()->get('parent.session'),
            $this->contexts->canActAsStaff($identity),
        );
    }

    private function identity(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
