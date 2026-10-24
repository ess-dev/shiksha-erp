<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\ReportCardNotifier;
use App\Domain\Exams\Enums\MarksReadiness;
use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\RemarkSuggestions;
use App\Domain\Exams\ReportCardAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Queries\ReportCardQuery;
use Illuminate\Support\Str;

class GenerateReportCards extends Action
{
    public const MAX_REMARK_LENGTH = 300;

    public function __construct(
        private readonly ReportCardAccess $access,
        private readonly RosterQuery $roster,
        private readonly ReportCardQuery $reportCards,
        private readonly AcademicSessionsQuery $sessions,
        private readonly SchoolSettingsQuery $settings,
        private readonly StaffRefQuery $staff,
        private readonly ReportCardNotifier $notifier,
    ) {}

    /**
     * The class teacher generates report cards from published marks for
     * students of their section. `$remarks` maps a student's ulid to the
     * class teacher's remark, or null for the suggested one. Each card keeps
     * a snapshot of itself as generated. A student may get one only when
     * every examined subject is entered and published and they have no report
     * card for this exam yet (or it was sent back or withdrawn). The cards go
     * for approval, or straight to parents when the school switched approval
     * off. Returns how many were generated.
     *
     * @param  array<string, string|null>  $remarks
     */
    public function handle(Identity $actor, Exam $exam, string $sectionUlid, array $remarks): int
    {
        $section = $this->access->preparerSectionOrFail($actor, $sectionUlid);
        $exam = $this->access->examOrFail($section, $exam);

        if ($remarks === []) {
            throw ReportCardException::nothingToDo();
        }

        $students = $this->studentsOf($section, array_keys($remarks));
        $ready = $this->access->readiness($exam, $section, array_column($students, 'key'));
        $existing = ReportCard::query()->where('exam_id', $exam->id)->whereIn('student_id', array_column($students, 'key'))->get()->keyBy('student_id');
        $session = $this->sessions->find() ?? throw ReportCardException::notFound();

        $cards = [];

        foreach ($students as $ulid => $student) {
            $card = $existing->get($student['key']);

            if ($card !== null && $card->status !== ReportCardStatus::Returned) {
                throw ReportCardException::alreadyPrepared($student['name']);
            }

            if (($ready[$student['key']] ?? null) !== MarksReadiness::Ready) {
                throw ReportCardException::marksNotReady($student['name']);
            }

            $remark = $remarks[$ulid];

            if ($remark !== null && mb_strlen(trim($remark)) > self::MAX_REMARK_LENGTH) {
                throw ReportCardException::remarkTooLong(self::MAX_REMARK_LENGTH);
            }

            $data = $this->reportCards->build($student['key'], $section->sectionKey, $session, $exam->ulid)
                ?? throw ReportCardException::marksNotReady($student['name']);

            $remark = $remark === null || trim($remark) === '' ? RemarkSuggestions::for($data->result->percent_tenths) : trim($remark);
            $data->remark = $remark;

            $cards[$ulid] = ['key' => $student['key'], 'remark' => $remark, 'snapshot' => $data->toArray()];
        }

        $status = $this->settings->reportCardApproval() ? ReportCardStatus::Submitted : ReportCardStatus::Published;
        $staffKey = $this->staff->forIdentity($actor)?->staffKey;

        $this->transaction(function () use ($cards, $exam, $section, $status, $staffKey) {
            foreach ($cards as $card) {
                ReportCard::query()->updateOrCreate(['exam_id' => $exam->id, 'student_id' => $card['key']], [
                    'ulid' => strtolower((string) Str::ulid()),
                    'section_id' => $section->sectionKey,
                    'kind' => ReportCardKind::Generated,
                    'status' => $status,
                    'remark' => $card['remark'],
                    'snapshot' => $card['snapshot'],
                    'file_path' => null,
                    'file_name' => null,
                    'mime_type' => null,
                    'size_bytes' => null,
                    'prepared_by_staff_id' => $staffKey,
                    'prepared_at' => now(),
                    'decided_by_staff_id' => $status === ReportCardStatus::Published ? $staffKey : null,
                    'decided_at' => $status === ReportCardStatus::Published ? now() : null,
                    'return_note' => null,
                ]);
            }

            $this->audit(new AuditEntry('report_cards.generated', 'exam', $exam->ulid, null, [
                'section' => $section->ulid,
                'students' => array_keys($cards),
                'status' => $status->value,
            ]));
        });

        $this->notifier->notify($status === ReportCardStatus::Published ? 'published' : 'submitted', $exam->ulid, $section->ulid, array_keys($cards));

        return count($cards);
    }

    /**
     * The named students on the section's roster today, or a 404.
     *
     * @param  list<string>  $ulids
     * @return array<string, array{key: int, name: string}>
     */
    private function studentsOf(SectionRefData $section, array $ulids): array
    {
        $roster = [];

        foreach ($this->roster->for($section->sectionKey, CalendarDate::today()) as $row) {
            $roster[$row->ulid] = ['key' => $row->studentKey, 'name' => $row->name];
        }

        $students = [];

        foreach ($ulids as $ulid) {
            $students[$ulid] = $roster[$ulid] ?? throw ReportCardException::notFound();
        }

        return $students;
    }
}
