<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\ExamNotifier;
use App\Domain\Exams\ExamDetails;
use App\Domain\Exams\ExamReach;
use App\Domain\Exams\ExamRules;
use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\StoresExamParts;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Database\UniqueConstraintViolationException;

class UpdateExam extends Action
{
    use StoresExamParts;

    public function __construct(
        private readonly ExamRules $rules,
        private readonly ExamReach $reach,
        private readonly ExamNotifier $notifier,
    ) {}

    /**
     * Changes a scheduled exam or one with marking open. Needs `exams.schedule`
     * over everything the exam applies to now (and after the change). Maximum
     * marks cannot change once marks are entered.
     */
    public function handle(Identity $actor, Exam $exam, ExamDetails $details): Exam
    {
        $this->reach->assertMayChange($actor, $exam);

        if (! $exam->status->isEditable()) {
            throw ExamException::notEditable();
        }

        if ($exam->hasMarks() && $details->maxMarks !== $exam->max_marks) {
            throw ExamException::badDateSheet('Maximum marks are locked because marks are already entered.');
        }

        $resolved = $this->rules->resolve($actor, $details, $exam, $exam->academic_year_id);
        $before = $this->summary($exam, [
            'class_ids' => $this->reach->examClassIds($exam),
            'campus_ids' => $this->reach->examCampusIds($exam),
            'all_classes' => $exam->applies_to_all_classes,
            'all_campuses' => $exam->applies_to_all_campuses,
            'sheet' => array_values($exam->dateSheet()->get(['subject_id', 'held_on', 'begins_at'])->map(fn ($row) => [
                'subject_id' => $row->subject_id, 'held_on' => $row->held_on, 'begins_at' => $row->begins_at,
            ])->all()),
        ]);

        try {
            $this->transaction(function () use ($exam, $details, $resolved, $before) {
                $exam->update([
                    'name' => $details->name,
                    'type' => $details->type,
                    'max_marks' => $details->maxMarks,
                    'starts_on' => $details->startsOn,
                    'ends_on' => $details->endsOn,
                    'instructions' => $details->instructions,
                    'applies_to_all_classes' => $resolved['all_classes'],
                    'applies_to_all_campuses' => $resolved['all_campuses'],
                    'show_on_calendar' => $details->showOnCalendar,
                ]);

                $this->storeParts($exam, $resolved);

                $this->audit(new AuditEntry('exams.exam_updated', 'exam', $exam->ulid, $before, $this->summary($exam, $resolved)));
            });
        } catch (UniqueConstraintViolationException) {
            throw ExamException::duplicateName();
        }

        if ($details->notifyParents) {
            $this->notifier->notify($exam->ulid, 'changed');
        }

        return $exam;
    }
}
