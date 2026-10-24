<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Exceptions\MarksException;
use App\Domain\Exams\MarksAccess;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamMarkSheet;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class ReopenMarkSheet extends Action
{
    public const MAX_REASON_LENGTH = 200;

    public function __construct(
        private readonly MarksAccess $access,
        private readonly StaffRefQuery $staff,
    ) {}

    /**
     * Takes one subject's published marks for a section back from parents so
     * they can be corrected and published again. Needs `marks.reopen` over the
     * section, a reason, and marking still open for the exam (once the whole
     * exam's results are published they are final). The marks themselves are
     * kept; every later change to them is audited.
     */
    public function handle(Identity $actor, Exam $exam, string $sectionUlid, string $subjectUlid, string $reason): void
    {
        [$section, $subject] = $this->access->pairOrFail($actor, $exam, $sectionUlid, $subjectUlid);

        if (! $section->isOverseer) {
            throw MarksException::notAllowed();
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw MarksException::reasonNeeded();
        }

        if ($exam->status !== ExamStatus::Marking) {
            throw MarksException::markingClosed();
        }

        $staffKey = $this->staff->forIdentity($actor)?->staffKey;

        $this->transaction(function () use ($exam, $section, $subject, $reason, $staffKey) {
            $sheet = ExamMarkSheet::query()
                ->where('exam_id', $exam->id)
                ->where('section_id', $section->section->sectionKey)
                ->where('subject_id', $subject->subjectKey)
                ->lockForUpdate()
                ->first();

            if ($sheet === null || ! $sheet->isPublished()) {
                throw MarksException::notPublished();
            }

            $sheet->update([
                'published_at' => null,
                'published_by_staff_id' => null,
                'reopened_at' => now(),
                'reopened_by_staff_id' => $staffKey,
                'reopen_reason' => $reason,
            ]);

            $this->audit(new AuditEntry('marks.reopened', 'exam', $exam->ulid, null, [
                'section' => $section->section->ulid,
                'subject' => $subject->ulid,
                'reason' => $reason,
            ]));
        });
    }
}
