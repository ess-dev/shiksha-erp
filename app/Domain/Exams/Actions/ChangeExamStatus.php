<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\ExamNotifier;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\ExamReach;
use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Database\UniqueConstraintViolationException;

class ChangeExamStatus extends Action
{
    public function __construct(
        private readonly ExamReach $reach,
        private readonly ExamNotifier $notifier,
    ) {}

    /**
     * Opens or closes marking, publishes every subject, cancels or restores an
     * exam. Needs `exams.schedule` over the exam, and also `results.publish`
     * to publish. Publishing here only changes the status (and tells parents):
     * marks entry and per-subject publishing arrive with the marks slice.
     * Restoring fails when a live exam already has the name.
     */
    public function handle(Identity $actor, Exam $exam, ExamStatus $to): Exam
    {
        $this->reach->assertMayChange($actor, $exam);

        if ($to === ExamStatus::Published) {
            $this->reach->assertMayChange($actor, $exam, 'results.publish');
        }

        $from = $exam->status;

        if (! $from->mayMoveTo($to)) {
            throw ExamException::badTransition($from->label(), $to->label());
        }

        try {
            $this->transaction(function () use ($exam, $from, $to) {
                $exam->update([
                    'status' => $to,
                    'published_at' => $to === ExamStatus::Published ? now() : $exam->published_at,
                ]);

                $this->audit(new AuditEntry('exams.status_changed', 'exam', $exam->ulid, ['status' => $from->value], ['status' => $to->value]));
            });
        } catch (UniqueConstraintViolationException) {
            throw ExamException::duplicateName();
        }

        if ($to === ExamStatus::Published) {
            $this->notifier->notify($exam->ulid, 'results_published');
        }

        return $exam;
    }
}
