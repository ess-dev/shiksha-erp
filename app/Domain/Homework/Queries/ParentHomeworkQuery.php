<?php

namespace App\Domain\Homework\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Homework\Data\ParentHomeworkItemData;
use App\Domain\Homework\Enums\ParentHomeworkState;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\HomeworkFileLinks;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\GuardianChildRef;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class ParentHomeworkQuery
{
    /** At most this many assignments are sent, newest due date first. */
    private const LIMIT = 150;

    public function __construct(
        private readonly StaffRefQuery $staff,
        private readonly HomeworkFileLinks $links,
    ) {}

    /**
     * The homework of the child's section in the viewed session, for the child's
     * own state on each (to do, handed in, missed). The child comes from
     * ParentPortalContextQuery, so it is one of the guardian's own and
     * `sectionKey` is the section it was enrolled in that session. Past sessions
     * are read-only: nothing can be uploaded there.
     *
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     * @return list<ParentHomeworkItemData>
     */
    public function get(GuardianChildRef $child, array $session): array
    {
        $today = CalendarDate::today();

        $assignments = [];

        foreach (HomeworkAssignment::query()
            ->with('files')
            ->where('academic_year_id', $session['key'])
            ->where('section_id', $child->sectionKey)
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get() as $found) {
            $assignments[] = $found;
        }

        if ($assignments === []) {
            return [];
        }

        $assignmentKeys = [];
        $subjectKeys = [];
        $teacherKeys = [];

        foreach ($assignments as $assignment) {
            $assignmentKeys[] = $assignment->id;
            $subjectKeys[$assignment->subject_id] = $assignment->subject_id;
            $teacherKeys[$assignment->created_by_staff_id] = $assignment->created_by_staff_id;
        }

        $subjects = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('subjects')
            ->whereIn('id', array_values($subjectKeys))->pluck('name', 'id')->all();
        $teachers = $this->staff->namesByKey(array_values($teacherKeys));
        $submissions = [];

        foreach (HomeworkSubmission::query()->with('files')
            ->whereIn('homework_assignment_id', $assignmentKeys)
            ->where('student_id', $child->studentKey)
            ->get() as $found) {
            $submissions[$found->homework_assignment_id] = $found;
        }

        $items = [];

        foreach ($assignments as $assignment) {
            $submission = $submissions[$assignment->id] ?? null;
            $state = $submission !== null ? ParentHomeworkState::Done : ($assignment->due_on < $today ? ParentHomeworkState::Missed : ParentHomeworkState::Due);

            $items[] = new ParentHomeworkItemData(
                $assignment->ulid,
                (string) ($subjects[$assignment->subject_id] ?? ''),
                (string) ($teachers[$assignment->created_by_staff_id] ?? ''),
                $assignment->title,
                $assignment->instructions,
                $assignment->assigned_on,
                $assignment->due_on,
                (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($assignment->due_on))->format('%r%a'),
                $assignment->submission_type,
                $state,
                $submission !== null && $submission->submitted_on !== null && $submission->submitted_on > $assignment->due_on,
                $submission === null ? null : $submission->feedback,
                $session['is_current'] && $state !== ParentHomeworkState::Done && $assignment->submission_type === SubmissionType::Upload,
                $this->links->attachments($assignment->files, $child->ulid),
                $submission === null ? [] : $this->links->submissions($submission->files, $child->ulid),
            );
        }

        return $items;
    }
}
