<?php

namespace App\Domain\Homework\Actions;

use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Homework\Contracts\HomeworkNotifier;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Homework\HomeworkFileStore;
use App\Domain\Homework\HomeworkRules;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkFile;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class CreateHomework extends Action
{
    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly HomeworkRules $rules,
        private readonly HomeworkFileStore $files,
        private readonly RosterQuery $roster,
        private readonly HomeworkNotifier $notifier,
    ) {}

    /**
     * A teacher sets homework for a subject of a section they teach. The due
     * date must be a school day after today. Attachments (up to 5, 10 MB each)
     * are checked before anything is stored. Families of the section are told
     * through the notifier once it is saved.
     *
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(Identity $actor, string $sectionUlid, string $subjectUlid, string $title, ?string $instructions, string $dueOn, SubmissionType $type, array $attachments = []): HomeworkAssignment
    {
        $pair = $this->access->pairOrFail($actor, $sectionUlid, $subjectUlid);
        $staffKey = $this->access->staffKey($actor) ?? throw HomeworkException::notAllowed();

        $title = $this->rules->title($title);
        $instructions = $this->rules->instructions($instructions);
        $dueOn = $this->rules->dueOn($dueOn);
        $checked = $this->files->check($attachments);

        $ulid = strtolower((string) Str::ulid());
        $stored = $this->files->store($checked, $ulid);

        try {
            $assignment = $this->transaction(function () use ($pair, $staffKey, $title, $instructions, $dueOn, $type, $ulid, $stored) {
                $assignment = HomeworkAssignment::query()->create([
                    'ulid' => $ulid,
                    'academic_year_id' => $pair->section->academicYearKey,
                    'section_id' => $pair->section->sectionKey,
                    'subject_id' => $pair->subjectKey,
                    'title' => $title,
                    'instructions' => $instructions,
                    'assigned_on' => CalendarDate::today(),
                    'due_on' => $dueOn,
                    'submission_type' => $type,
                    'created_by_staff_id' => $staffKey,
                ]);

                foreach ($stored as $position => $file) {
                    HomeworkFile::query()->create([
                        'ulid' => $file['ulid'],
                        'homework_assignment_id' => $assignment->id,
                        'file_path' => $file['path'],
                        'file_name' => $file['name'],
                        'mime_type' => $file['mime'],
                        'size_bytes' => $file['size'],
                        'position' => $position,
                    ]);
                }

                $this->audit(new AuditEntry('homework.created', 'homework_assignment', $ulid, null, [
                    'section' => $pair->section->ulid,
                    'subject' => $pair->subjectUlid,
                    'title' => $title,
                    'due_on' => $dueOn,
                    'submission_type' => $type->value,
                    'attachments' => count($stored),
                ]));

                return $assignment;
            });
        } catch (Throwable $e) {
            $this->files->delete(array_column($stored, 'path'));

            throw $e;
        }

        $this->notifier->notify('assigned', $ulid, $pair->section->ulid, array_map(
            fn ($row) => $row->ulid,
            $this->roster->for($pair->section->sectionKey, CalendarDate::today()),
        ));

        return $assignment;
    }
}
