<?php

namespace App\Domain\Homework\Actions;

use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Homework\Contracts\HomeworkNotifier;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;

class DeleteHomework extends Action
{
    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly RosterQuery $roster,
        private readonly SectionDirectoryQuery $sections,
        private readonly HomeworkNotifier $notifier,
    ) {}

    /**
     * The teacher who set the homework deletes it at any point, even after
     * students handed in. It is a soft delete: parents and the teacher no longer
     * see it, but the submissions, files and audit trail stay.
     */
    public function handle(Identity $actor, string $assignmentUlid): void
    {
        $assignment = $this->access->manageableOrFail($actor, $assignmentUlid);

        $this->transaction(function () use ($assignment) {
            $assignment->delete();

            $this->audit(new AuditEntry('homework.deleted', 'homework_assignment', $assignment->ulid, [
                'title' => $assignment->title,
                'due_on' => $assignment->due_on,
                'submissions' => $assignment->submissions()->count(),
            ]));
        });

        $section = $this->sections->byKeys([$assignment->section_id])[$assignment->section_id] ?? null;

        if ($section !== null) {
            $this->notifier->notify('removed', $assignment->ulid, $section->ulid, array_map(
                fn ($row) => $row->ulid,
                $this->roster->for($section->sectionKey, CalendarDate::today()),
            ));
        }
    }
}
