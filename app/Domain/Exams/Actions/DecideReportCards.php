<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\ReportCardNotifier;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\ReportCardAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

class DecideReportCards extends Action
{
    public function __construct(
        private readonly ReportCardAccess $access,
        private readonly StaffRefQuery $staff,
        private readonly ReportCardNotifier $notifier,
    ) {}

    /**
     * Publishes to parents, or sends back to the class teacher with a note,
     * the report cards of one section and exam that wait for approval: all of
     * them, or only the students named. Needs `reportcards.approve` over the
     * section. Returns how many were decided.
     *
     * @param  list<string>|null  $studentUlids
     */
    public function handle(Identity $actor, Exam $exam, string $sectionUlid, bool $approve, ?string $note = null, ?array $studentUlids = null): int
    {
        $section = $this->access->approverSectionOrFail($actor, $sectionUlid);
        $note = $note === null ? null : trim($note);

        if (! $approve && ($note === null || mb_strlen($note) < 3 || mb_strlen($note) > 200)) {
            throw ReportCardException::noteNeeded();
        }

        $staffKey = $this->staff->forIdentity($actor)?->staffKey;

        $decided = $this->transaction(function () use ($exam, $section, $approve, $note, $studentUlids, $staffKey) {
            $query = ReportCard::query()
                ->where('exam_id', $exam->id)
                ->where('section_id', $section->sectionKey)
                ->where('status', ReportCardStatus::Submitted->value)
                ->lockForUpdate();

            if ($studentUlids !== null) {
                $query->whereIn('student_id', DB::connection(config('multitenancy.tenant_database_connection_name'))
                    ->table('students')->whereIn('ulid', $studentUlids)->pluck('id'));
            }

            $cards = $query->get();

            if ($cards->isEmpty()) {
                throw ReportCardException::nothingToDo();
            }

            foreach ($cards as $card) {
                $card->update([
                    'status' => $approve ? ReportCardStatus::Published : ReportCardStatus::Returned,
                    'decided_by_staff_id' => $staffKey,
                    'decided_at' => now(),
                    'return_note' => $approve ? null : $note,
                ]);
            }

            $this->audit(new AuditEntry($approve ? 'report_cards.approved' : 'report_cards.returned', 'exam', $exam->ulid, null, array_filter([
                'section' => $section->ulid,
                'report_cards' => array_values($cards->pluck('ulid')->all()),
                'note' => $note,
            ], fn ($value) => $value !== null)));

            return $cards;
        });

        $studentKeys = $decided->pluck('student_id')->all();
        $ulids = array_values(DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')->whereIn('id', $studentKeys)->pluck('ulid')->all());
        $this->notifier->notify($approve ? 'published' : 'returned', $exam->ulid, $section->ulid, array_map(strval(...), $ulids));

        return $decided->count();
    }
}
