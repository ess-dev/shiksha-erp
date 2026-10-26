<?php

namespace App\Domain\Homework\Actions;

use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Homework\Contracts\HomeworkNotifier;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Homework\HomeworkRules;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;

class SaveHomeworkFeedback extends Action
{
    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly RosterQuery $roster,
        private readonly SectionDirectoryQuery $sections,
        private readonly HomeworkNotifier $notifier,
    ) {}

    /**
     * The teacher writes (or clears) the short feedback parents read, for
     * students who handed in. Only rows that differ are saved; students who
     * got new feedback are told through the notifier. Returns how many changed.
     *
     * @param  array<string, string|null>  $feedback  student ulid => text (null or blank clears it)
     */
    public function handle(Identity $actor, string $assignmentUlid, array $feedback): int
    {
        $assignment = $this->access->manageableOrFail($actor, $assignmentUlid);
        $staffKey = $this->access->staffKey($actor) ?? throw HomeworkException::notAllowed();

        $roster = [];

        foreach ($this->roster->for($assignment->section_id, CalendarDate::today()) as $row) {
            $roster[$row->ulid] = $row;
        }

        $submissions = $assignment->submissions()->get()->keyBy('student_id');
        $changes = [];

        foreach ($feedback as $studentUlid => $text) {
            $row = $roster[$studentUlid] ?? throw HomeworkException::notFound();
            $text = $text === null ? null : trim($text);
            $text = $text === '' ? null : $text;

            if ($text !== null && mb_strlen($text) > HomeworkRules::FEEDBACK_MAX) {
                throw HomeworkException::feedbackTooLong(HomeworkRules::FEEDBACK_MAX);
            }

            $submission = $submissions->get($row->studentKey);

            if ($submission === null) {
                if ($text !== null) {
                    throw HomeworkException::notFound();
                }

                continue;
            }

            if ($submission->feedback !== $text) {
                $changes[] = [$row->ulid, $submission, $text];
            }
        }

        if ($changes === []) {
            return 0;
        }

        $this->transaction(function () use ($assignment, $changes, $staffKey) {
            foreach ($changes as [, $submission, $text]) {
                $submission->update([
                    'feedback' => $text,
                    'feedback_by_staff_id' => $text === null ? null : $staffKey,
                    'feedback_at' => $text === null ? null : now(),
                ]);
            }

            $this->audit(new AuditEntry('homework.feedback_saved', 'homework_assignment', $assignment->ulid, null, [
                'students' => array_map(fn ($change) => $change[0], $changes),
            ]));
        });

        $told = array_values(array_map(fn ($change) => $change[0], array_filter($changes, fn ($change) => $change[2] !== null)));
        $section = $this->sections->byKeys([$assignment->section_id])[$assignment->section_id] ?? null;

        if ($told !== [] && $section !== null) {
            $this->notifier->notify('feedback', $assignment->ulid, $section->ulid, $told);
        }

        return count($changes);
    }
}
