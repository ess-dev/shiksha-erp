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
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateHomework extends Action
{
    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly HomeworkRules $rules,
        private readonly HomeworkFileStore $files,
        private readonly RosterQuery $roster,
        private readonly SectionDirectoryQuery $sections,
        private readonly HomeworkNotifier $notifier,
    ) {}

    /**
     * The teacher who set the homework changes it at any point: title,
     * instructions, due date, attachments (add or remove) and, while nobody has
     * handed in yet, the way of handing in. The due date is checked again only
     * when it changes, so a closed assignment can still be corrected. Families
     * are told when something they see changed.
     *
     * @param  list<UploadedFile>  $newAttachments
     * @param  list<string>  $removeFileUlids
     */
    public function handle(Identity $actor, string $assignmentUlid, string $title, ?string $instructions, string $dueOn, SubmissionType $type, array $newAttachments = [], array $removeFileUlids = []): HomeworkAssignment
    {
        $assignment = $this->access->manageableOrFail($actor, $assignmentUlid);

        $title = $this->rules->title($title);
        $instructions = $this->rules->instructions($instructions);
        $dueOn = $dueOn === $assignment->due_on ? $dueOn : $this->rules->dueOn($dueOn);

        if ($type !== $assignment->submission_type && $assignment->submissions()->exists()) {
            throw HomeworkException::typeLocked();
        }

        $existing = $assignment->files()->get();
        $removing = $existing->whereIn('ulid', $removeFileUlids)->values();
        $checked = $this->files->check($newAttachments, $existing->count() - $removing->count());
        $stored = $this->files->store($checked, $assignment->ulid);

        $before = ['title' => $assignment->title, 'instructions' => $assignment->instructions, 'due_on' => $assignment->due_on, 'submission_type' => $assignment->submission_type->value];
        $after = ['title' => $title, 'instructions' => $instructions, 'due_on' => $dueOn, 'submission_type' => $type->value];
        $changed = $before !== $after || $stored !== [] || $removing->isNotEmpty();

        if (! $changed) {
            $this->files->delete(array_column($stored, 'path'));

            return $assignment;
        }

        try {
            $this->transaction(function () use ($assignment, $after, $before, $stored, $removing, $existing) {
                $assignment->update($after);

                $position = (int) $existing->max('position') + 1;

                foreach ($stored as $file) {
                    HomeworkFile::query()->create([
                        'ulid' => $file['ulid'],
                        'homework_assignment_id' => $assignment->id,
                        'file_path' => $file['path'],
                        'file_name' => $file['name'],
                        'mime_type' => $file['mime'],
                        'size_bytes' => $file['size'],
                        'position' => $position++,
                    ]);
                }

                HomeworkFile::query()->whereIn('id', $removing->pluck('id'))->delete();

                $this->audit(new AuditEntry(
                    'homework.updated',
                    'homework_assignment',
                    $assignment->ulid,
                    [...$before, 'attachments_removed' => $removing->pluck('file_name')->all()],
                    [...$after, 'attachments_added' => array_column($stored, 'name')],
                ));
            });
        } catch (Throwable $e) {
            $this->files->delete(array_column($stored, 'path'));

            throw $e;
        }

        $removedPaths = [];

        foreach ($removing as $removedFile) {
            $removedPaths[] = $removedFile->file_path;
        }

        $this->files->delete($removedPaths);

        $section = $this->sections->byKeys([$assignment->section_id])[$assignment->section_id] ?? null;

        if ($section !== null) {
            $this->notifier->notify('changed', $assignment->ulid, $section->ulid, array_map(
                fn ($row) => $row->ulid,
                $this->roster->for($section->sectionKey, CalendarDate::today()),
            ));
        }

        return $assignment->refresh();
    }
}
