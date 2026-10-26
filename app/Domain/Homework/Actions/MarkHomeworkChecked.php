<?php

namespace App\Domain\Homework\Actions;

use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use Illuminate\Support\Str;

class MarkHomeworkChecked extends Action
{
    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly RosterQuery $roster,
    ) {}

    /**
     * The teacher marks students' work checked or takes the mark back, for
     * chosen students or the whole section (`$studentUlids` null). Notebook work
     * is handed in by being checked, so checking creates the student's
     * submission and un-checking removes it again (with any feedback on it).
     * Uploaded work can only be checked once the parent has uploaded it; the
     * mark is then a review flag and un-checking leaves the upload alone. Only
     * students who change are touched; returns how many.
     *
     * @param  list<string>|null  $studentUlids
     */
    public function handle(Identity $actor, string $assignmentUlid, ?array $studentUlids, bool $checked): int
    {
        $assignment = $this->access->manageableOrFail($actor, $assignmentUlid);
        $staffKey = $this->access->staffKey($actor) ?? throw HomeworkException::notAllowed();
        $today = CalendarDate::today();

        $roster = [];

        foreach ($this->roster->for($assignment->section_id, $today) as $row) {
            $roster[$row->ulid] = $row;
        }

        if ($studentUlids !== null) {
            foreach ($studentUlids as $ulid) {
                if (! isset($roster[$ulid])) {
                    throw HomeworkException::notFound();
                }
            }
        }

        $targets = $studentUlids === null ? $roster : array_intersect_key($roster, array_flip($studentUlids));
        $existing = $assignment->submissions()->whereIn('student_id', array_map(fn ($row) => $row->studentKey, $targets))->get()->keyBy('student_id');
        $isNotebook = $assignment->submission_type === SubmissionType::Notebook;

        $changes = [];

        foreach ($targets as $row) {
            /** @var HomeworkSubmission|null $submission */
            $submission = $existing->get($row->studentKey);

            if ($checked && ($submission === null ? $isNotebook : $submission->checked_on === null)) {
                $changes[] = [$row, $submission];
            } elseif (! $checked && $submission !== null && $submission->checked_on !== null) {
                $changes[] = [$row, $submission];
            }
        }

        if ($changes === []) {
            return 0;
        }

        $this->transaction(function () use ($assignment, $changes, $checked, $isNotebook, $staffKey, $today) {
            foreach ($changes as [$row, $submission]) {
                if ($checked && $submission === null) {
                    HomeworkSubmission::query()->create([
                        'ulid' => strtolower((string) Str::ulid()),
                        'homework_assignment_id' => $assignment->id,
                        'student_id' => $row->studentKey,
                        'checked_on' => $today,
                        'checked_by_staff_id' => $staffKey,
                    ]);
                } elseif ($checked) {
                    $submission->update(['checked_on' => $today, 'checked_by_staff_id' => $staffKey]);
                } elseif ($isNotebook || $submission->submitted_on === null) {
                    $submission->delete();
                } else {
                    $submission->update(['checked_on' => null, 'checked_by_staff_id' => null]);
                }
            }

            $this->audit(new AuditEntry(
                $checked ? 'homework.checked' : 'homework.unchecked',
                'homework_assignment',
                $assignment->ulid,
                null,
                ['students' => array_map(fn ($change) => $change[0]->ulid, $changes)],
            ));
        });

        return count($changes);
    }
}
