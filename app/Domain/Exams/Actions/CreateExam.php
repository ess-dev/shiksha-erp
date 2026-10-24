<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\ExamNotifier;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\ExamDetails;
use App\Domain\Exams\ExamRules;
use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\StoresExamParts;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class CreateExam extends Action
{
    use StoresExamParts;

    public function __construct(
        private readonly ExamRules $rules,
        private readonly SectionDirectoryQuery $sections,
        private readonly StaffRefQuery $staff,
        private readonly ExamNotifier $notifier,
    ) {}

    /**
     * Schedules a unit test or exam for the current academic year. The person
     * must hold `exams.schedule` over every class and campus it applies to.
     * Parents are told through the ExamNotifier after it is stored, when asked.
     */
    public function handle(Identity $actor, ExamDetails $details): Exam
    {
        $year = $this->sections->currentYearRef() ?? throw ExamException::noAcademicYear();
        $resolved = $this->rules->resolve($actor, $details, null, $year['key']);
        $creator = $this->staff->forIdentity($actor);

        try {
            $exam = $this->transaction(function () use ($details, $resolved, $year, $creator) {
                $exam = Exam::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'academic_year_id' => $year['key'],
                    'name' => $details->name,
                    'type' => $details->type,
                    'max_marks' => $details->maxMarks,
                    'starts_on' => $details->startsOn,
                    'ends_on' => $details->endsOn,
                    'status' => ExamStatus::Scheduled,
                    'instructions' => $details->instructions,
                    'applies_to_all_classes' => $resolved['all_classes'],
                    'applies_to_all_campuses' => $resolved['all_campuses'],
                    'show_on_calendar' => $details->showOnCalendar,
                    'created_by_staff_id' => $creator?->staffKey,
                ]);

                $this->storeParts($exam, $resolved);

                $this->audit(new AuditEntry('exams.exam_created', 'exam', $exam->ulid, null, $this->summary($exam, $resolved)));

                return $exam;
            });
        } catch (UniqueConstraintViolationException) {
            throw ExamException::duplicateName();
        }

        if ($details->notifyParents) {
            $this->notifier->notify($exam->ulid, 'scheduled');
        }

        return $exam;
    }
}
