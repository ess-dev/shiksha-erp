<?php

namespace App\Domain\Homework\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Homework\Contracts\HomeworkNotifier;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkFileStore;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\Homework\Models\HomeworkSubmissionFile;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Queries\GuardianChildrenQuery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class SubmitHomework extends Action
{
    public function __construct(
        private readonly HomeworkFileStore $files,
        private readonly SectionDirectoryQuery $sections,
        private readonly HomeworkNotifier $notifier,
        private readonly GuardianChildrenQuery $children,
    ) {}

    /**
     * A guardian uploads their child's work (1 to 5 files) for homework the
     * teacher asked to be uploaded in the app. Late uploads are accepted and
     * shown as late. The child comes from the parent context, so it is one of
     * the guardian's own in the current session; the homework must belong to
     * the child's section. One hand-in per child: it is not replaced.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public function handle(Identity $actor, GuardianChildRef $child, string $assignmentUlid, array $uploads): HomeworkSubmission
    {
        $guardianKey = ($this->children->guardian($actor) ?? throw HomeworkException::notAllowed())->id;

        $assignment = HomeworkAssignment::query()->where('ulid', $assignmentUlid)->where('section_id', $child->sectionKey)->first()
            ?? throw HomeworkException::notFound();

        if ($assignment->submission_type !== SubmissionType::Upload) {
            throw HomeworkException::notUploadType();
        }

        if ($uploads === []) {
            throw HomeworkException::nothingToSubmit();
        }

        if (HomeworkSubmission::query()->where('homework_assignment_id', $assignment->id)->where('student_id', $child->studentKey)->exists()) {
            throw HomeworkException::alreadySubmitted();
        }

        $checked = $this->files->check($uploads);
        $stored = $this->files->store($checked, "{$assignment->ulid}/submissions/{$child->ulid}");
        $ulid = strtolower((string) Str::ulid());
        $today = CalendarDate::today();

        try {
            $submission = $this->transaction(function () use ($assignment, $child, $guardianKey, $stored, $ulid, $today) {
                $submission = HomeworkSubmission::query()->create([
                    'ulid' => $ulid,
                    'homework_assignment_id' => $assignment->id,
                    'student_id' => $child->studentKey,
                    'submitted_on' => $today,
                    'submitted_at' => now(),
                    'submitted_by_guardian_id' => $guardianKey,
                ]);

                foreach ($stored as $position => $file) {
                    HomeworkSubmissionFile::query()->create([
                        'ulid' => $file['ulid'],
                        'homework_submission_id' => $submission->id,
                        'file_path' => $file['path'],
                        'file_name' => $file['name'],
                        'mime_type' => $file['mime'],
                        'size_bytes' => $file['size'],
                        'position' => $position,
                    ]);
                }

                $this->audit(new AuditEntry('homework.submitted', 'homework_assignment', $assignment->ulid, null, [
                    'student' => $child->ulid,
                    'files' => count($stored),
                    'is_late' => $today > $assignment->due_on,
                ]));

                return $submission;
            });
        } catch (Throwable $e) {
            $this->files->delete(array_column($stored, 'path'));

            throw $e;
        }

        $section = $this->sections->byKeys([$assignment->section_id])[$assignment->section_id] ?? null;

        if ($section !== null) {
            $this->notifier->notify('submitted', $assignment->ulid, $section->ulid, [$child->ulid]);
        }

        return $submission;
    }
}
