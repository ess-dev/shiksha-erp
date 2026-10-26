<?php

namespace App\Domain\Homework\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Homework\Data\HomeworkDetailData;
use App\Domain\Homework\Data\HomeworkFormOptionsData;
use App\Domain\Homework\Data\HomeworkListItemData;
use App\Domain\Homework\Data\HomeworkPair;
use App\Domain\Homework\Data\HomeworkScreenData;
use App\Domain\Homework\Data\HomeworkSectionFilterData;
use App\Domain\Homework\Data\HomeworkSectionOptionData;
use App\Domain\Homework\Data\HomeworkStudentRowData;
use App\Domain\Homework\Data\HomeworkSubjectOptionData;
use App\Domain\Homework\Enums\StudentHomeworkStatus;
use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\HomeworkAccess;
use App\Domain\Homework\HomeworkFileLinks;
use App\Domain\Homework\HomeworkFileStore;
use App\Domain\Homework\HomeworkRules;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the teacher's Homework screen: the assignments of every section the
 * person may see (newest first, at most 100), the selected one with each
 * student's status, and the options for the New assignment dialog.
 */
class HomeworkScreenQuery
{
    private const LIMIT = 100;

    public function __construct(
        private readonly HomeworkAccess $access,
        private readonly RosterQuery $roster,
        private readonly StaffRefQuery $staff,
        private readonly HomeworkFileLinks $links,
        private readonly SchoolDayQuery $days,
    ) {}

    public function get(Identity $actor, ?string $sectionUlid, ?string $assignmentUlid): HomeworkScreenData
    {
        $today = CalendarDate::today();
        $sections = $this->access->viewableSections($actor);
        $filter = $sectionUlid !== null && in_array($sectionUlid, array_map(fn (SectionRefData $s) => $s->ulid, $sections), true) ? $sectionUlid : null;
        $inList = array_values(array_filter($sections, fn (SectionRefData $s) => $filter === null || $s->ulid === $filter));
        $bySection = [];

        foreach ($inList as $section) {
            $bySection[$section->sectionKey] = $section;
        }

        $assignments = [];

        if ($bySection !== []) {
            foreach (HomeworkAssignment::query()
                ->whereIn('section_id', array_keys($bySection))
                ->orderByDesc('assigned_on')
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->get() as $found) {
                $assignments[] = $found;
            }
        }

        $assignmentKeys = [];
        $subjectKeys = [];

        foreach ($assignments as $assignment) {
            $assignmentKeys[] = $assignment->id;
            $subjectKeys[$assignment->subject_id] = $assignment->subject_id;
        }

        $subjects = $subjectKeys === [] ? [] : $this->connection()->table('subjects')->whereIn('id', array_values($subjectKeys))->pluck('name', 'id')->all();
        $counts = $this->counts($assignmentKeys);

        $items = [];

        foreach ($assignments as $assignment) {
            $section = $bySection[$assignment->section_id];
            $count = $counts[$assignment->id] ?? ['submitted' => 0, 'to_check' => 0];

            $items[] = new HomeworkListItemData(
                $assignment->ulid,
                $assignment->title,
                $section->label(),
                (string) ($subjects[$assignment->subject_id] ?? ''),
                $assignment->due_on,
                $this->daysLeft($today, $assignment->due_on),
                $assignment->submission_type,
                $count['submitted'],
                $section->enrolledCount,
                $assignment->submission_type === SubmissionType::Upload ? $count['to_check'] : 0,
            );
        }

        $selected = $assignments[0] ?? null;

        foreach ($assignments as $assignment) {
            if ($assignment->ulid === $assignmentUlid) {
                $selected = $assignment;
            }
        }

        $pairs = $this->access->pairs($actor);

        return new HomeworkScreenData(
            $today,
            $pairs !== [],
            array_map(fn (SectionRefData $s) => new HomeworkSectionFilterData($s->ulid, $s->label()), $sections),
            $filter,
            $items,
            $selected?->ulid,
            $selected === null ? null : $this->detail($actor, $selected, $bySection[$selected->section_id], $today),
            $pairs === [] ? null : $this->form($pairs, $today),
        );
    }

    private function detail(Identity $actor, HomeworkAssignment $assignment, SectionRefData $section, string $today): HomeworkDetailData
    {
        $subject = (string) $this->connection()->table('subjects')->where('id', $assignment->subject_id)->value('name');
        $subjectUlid = (string) $this->connection()->table('subjects')->where('id', $assignment->subject_id)->value('ulid');
        $submissions = [];

        foreach ($assignment->submissions()->with('files')->get() as $found) {
            $submissions[$found->student_id] = $found;
        }

        $isUpload = $assignment->submission_type === SubmissionType::Upload;
        $rows = [];
        $toCheck = 0;

        foreach ($this->roster->for($section->sectionKey, $today) as $student) {
            $submission = $submissions[$student->studentKey] ?? null;

            if ($submission !== null && $isUpload && $submission->submitted_on !== null && $submission->checked_on === null && $submission->feedback === null) {
                $toCheck++;
            }

            $rows[] = new HomeworkStudentRowData(
                $student->ulid,
                $student->rollNumber,
                $student->name,
                $this->status($submission, $assignment, $today),
                $submission === null ? null : ($submission->submitted_on ?? $submission->checked_on),
                $submission !== null && $submission->checked_on !== null,
                $submission === null ? null : $submission->feedback,
                $submission === null ? [] : $this->links->submissions($submission->files),
            );
        }

        $count = $this->counts([$assignment->id])[$assignment->id] ?? ['submitted' => 0, 'to_check' => 0];
        $assignedBy = $this->staff->namesByKey([$assignment->created_by_staff_id])[$assignment->created_by_staff_id] ?? '';

        return new HomeworkDetailData(
            $assignment->ulid,
            $assignment->title,
            $section->ulid,
            $section->label(),
            $subjectUlid,
            $subject,
            $assignment->instructions,
            $assignment->assigned_on,
            $assignedBy,
            $assignment->due_on,
            $this->daysLeft($today, $assignment->due_on),
            $assignment->submission_type,
            $count['submitted'],
            $section->enrolledCount,
            $toCheck,
            $this->access->mayManage($actor, $assignment),
            $this->links->attachments($assignment->files),
            $rows,
        );
    }

    private function status(?HomeworkSubmission $submission, HomeworkAssignment $assignment, string $today): StudentHomeworkStatus
    {
        if ($submission === null) {
            return $assignment->due_on < $today ? StudentHomeworkStatus::Missing : StudentHomeworkStatus::NotYet;
        }

        return $submission->submitted_on !== null && $submission->submitted_on > $assignment->due_on ? StudentHomeworkStatus::Late : StudentHomeworkStatus::Submitted;
    }

    /**
     * @param  list<int>  $assignmentKeys
     * @return array<int, array{submitted: int, to_check: int}>
     */
    private function counts(array $assignmentKeys): array
    {
        if ($assignmentKeys === []) {
            return [];
        }

        $counts = [];

        foreach (HomeworkSubmission::query()
            ->whereIn('homework_assignment_id', $assignmentKeys)
            ->selectRaw('homework_assignment_id, count(*) as submitted, sum(case when submitted_on is not null and checked_on is null and feedback is null then 1 else 0 end) as to_check')
            ->groupBy('homework_assignment_id')
            ->get() as $row) {
            $counts[(int) $row->getAttribute('homework_assignment_id')] = ['submitted' => (int) $row->getAttribute('submitted'), 'to_check' => (int) $row->getAttribute('to_check')];
        }

        return $counts;
    }

    /**
     * @param  list<HomeworkPair>  $pairs
     */
    private function form(array $pairs, string $today): HomeworkFormOptionsData
    {
        $grouped = [];

        foreach ($pairs as $pair) {
            $key = $pair->section->ulid;
            $grouped[$key]['label'] = $pair->section->label();
            $grouped[$key]['subjects'][] = new HomeworkSubjectOptionData($pair->subjectUlid, $pair->subjectName);
        }

        $options = [];

        foreach ($grouped as $ulid => $group) {
            $options[] = new HomeworkSectionOptionData($ulid, $group['label'], $group['subjects']);
        }

        $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
        $window = $this->days->range($tomorrow, (new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d'));
        $open = array_keys(array_filter($window, fn ($check) => $check->isSchoolDay));

        return new HomeworkFormOptionsData(
            $options,
            $tomorrow,
            $open[1] ?? $open[0] ?? $tomorrow,
            HomeworkFileStore::MAX_FILES,
            (int) (HomeworkFileStore::MAX_BYTES / 1024 / 1024),
            HomeworkRules::TITLE_MAX,
            HomeworkRules::INSTRUCTIONS_MAX,
            HomeworkRules::FEEDBACK_MAX,
        );
    }

    private function daysLeft(string $today, string $dueOn): int
    {
        return (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($dueOn))->format('%r%a');
    }

    private function connection(): Connection
    {
        return DB::connection(config('multitenancy.tenant_database_connection_name'));
    }
}
