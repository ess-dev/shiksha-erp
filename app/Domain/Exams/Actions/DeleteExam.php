<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\ExamReach;
use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class DeleteExam extends Action
{
    public function __construct(private readonly ExamReach $reach) {}

    /**
     * Removes an exam that has no marks and was never published (its classes,
     * campuses and date sheet go with it). The audit event keeps its name and
     * dates. Anything with marks or results is cancelled instead.
     */
    public function handle(Identity $actor, Exam $exam): void
    {
        $this->reach->assertMayChange($actor, $exam);

        if ($exam->status === ExamStatus::Published || $exam->hasMarks()) {
            throw ExamException::cannotDelete();
        }

        $this->transaction(function () use ($exam) {
            $before = [
                'name' => $exam->name,
                'type' => $exam->type->value,
                'starts_on' => $exam->starts_on,
                'ends_on' => $exam->ends_on,
                'status' => $exam->status->value,
            ];

            $exam->delete();

            $this->audit(new AuditEntry('exams.exam_deleted', 'exam', $exam->ulid, $before, null));
        });
    }
}
