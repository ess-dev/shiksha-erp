<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Access\Queries\PermissionHoldersQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Exams\Data\MarksExamOptionData;
use App\Domain\Exams\Data\MarksSectionOptionData;
use App\Domain\Exams\Data\ReportCardRowData;
use App\Domain\Exams\Data\ReportCardsScreenData;
use App\Domain\Exams\Data\ReportCardSummaryData;
use App\Domain\Exams\Data\ReportCardViewData;
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
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\ReportCardData;
use App\Domain\Students\Queries\ReportCardQuery;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * The class teacher's Report cards screen: their classes, the exams of the
 * chosen class, and each student on today's roster with how ready their
 * marks are and their report card for that exam. A requested class or exam
 * the person cannot open falls back to their first class and the latest exam.
 */
class ReportCardsScreenQuery
{
    public function __construct(
        private readonly ReportCardAccess $access,
        private readonly RosterQuery $roster,
        private readonly SchoolSettingsQuery $settings,
        private readonly PermissionHoldersQuery $holders,
        private readonly ReportCardQuery $reportCards,
        private readonly AcademicSessionsQuery $sessions,
        private readonly StaffRefQuery $staff,
    ) {}

    public function get(Identity $actor, ?string $sectionUlid, ?string $examUlid): ReportCardsScreenData
    {
        $sections = $this->access->preparerSections($actor);
        $section = $this->pickSection($sections, $sectionUlid);
        $exams = $section === null ? [] : $this->access->examsFor($section);

        $exam = null;

        foreach ($exams as $candidate) {
            if ($candidate->ulid === $examUlid) {
                $exam = $candidate;
            }
        }

        $exam ??= $exams === [] ? null : $exams[count($exams) - 1];

        $rows = $section === null || $exam === null ? [] : $this->rows($section, $exam);
        $count = fn (?ReportCardStatus $status) => count(array_filter($rows, fn (ReportCardRowData $row) => $row->card?->status === $status));
        $approval = $this->settings->reportCardApproval();

        return new ReportCardsScreenData(
            sections: array_map(fn (SectionRefData $s) => new MarksSectionOptionData($s->ulid, $s->label(), $s->campusName), $sections),
            section_ulid: $section?->ulid,
            exams: array_map(fn (Exam $e) => new MarksExamOptionData($e->ulid, $e->name, $e->max_marks, $e->status), $exams),
            exam_ulid: $exam?->ulid,
            exam_name: $exam?->name,
            approval_required: $approval,
            approvers: $approval && $section !== null ? $this->approvers($section) : null,
            not_started_count: $count(null),
            sent_back_count: $count(ReportCardStatus::Returned),
            awaiting_count: $count(ReportCardStatus::Submitted),
            published_count: $count(ReportCardStatus::Published),
            rows: $rows,
        );
    }

    /**
     * A new report card as it would be generated now, with the suggested
     * remark, for the class teacher to check before sending it.
     */
    public function preview(Identity $actor, string $sectionUlid, string $examUlid, string $studentUlid): ?ReportCardData
    {
        $section = $this->access->preparerSectionOrFail($actor, $sectionUlid);
        $exam = $this->examOf($section, $examUlid);
        $session = $this->sessions->find();

        foreach ($this->roster->for($section->sectionKey, CalendarDate::today()) as $row) {
            if ($row->ulid === $studentUlid && $session !== null) {
                $card = $this->reportCards->build($row->studentKey, $section->sectionKey, $session, $exam->ulid);

                if ($card !== null) {
                    $card->remark = RemarkSuggestions::for($card->result->percent_tenths);
                }

                return $card;
            }
        }

        throw ReportCardException::notFound();
    }

    /**
     * An existing report card of one of the person's classes.
     */
    public function view(Identity $actor, string $reportCardUlid): ReportCardViewData
    {
        $card = ReportCard::query()->where('ulid', $reportCardUlid)->first() ?? throw ReportCardException::notFound();
        $sections = array_filter($this->access->preparerSections($actor), fn (SectionRefData $s) => $s->sectionKey === $card->section_id);

        if ($sections === []) {
            throw ReportCardException::notFound();
        }

        return $this->viewData($card, fn (ReportCard $file) => URL::temporarySignedRoute('report-cards.file', now()->addMinutes(5), ['reportCard' => $file->ulid]));
    }

    /**
     * @param  callable(ReportCard): string  $fileUrl
     */
    public function viewData(ReportCard $card, callable $fileUrl): ReportCardViewData
    {
        $exam = $card->exam;
        $names = $this->staff->namesByKey($card->prepared_by_staff_id === null ? [] : [$card->prepared_by_staff_id]);
        $snapshot = $card->snapshot === null ? null : ReportCardData::from($card->snapshot);

        return new ReportCardViewData(
            ulid: $card->ulid,
            kind: $card->kind,
            status: $card->status,
            exam_name: $exam->name,
            session_name: $snapshot !== null ? $snapshot->session_name : (string) $this->sessions->find()['name'],
            prepared_by: $card->prepared_by_staff_id === null ? 'the class teacher' : ($names[$card->prepared_by_staff_id] ?? 'the class teacher'),
            prepared_on: (string) $this->schoolDay($card->prepared_at),
            card: $snapshot,
            file_url: $card->kind === ReportCardKind::File ? $fileUrl($card) : null,
            file_name: $card->file_name,
            is_image: $card->mime_type !== null && str_starts_with($card->mime_type, 'image/'),
        );
    }

    /**
     * @return list<ReportCardRowData>
     */
    private function rows(SectionRefData $section, Exam $exam): array
    {
        $roster = $this->roster->for($section->sectionKey, CalendarDate::today());
        $keys = array_map(fn ($row) => $row->studentKey, $roster);
        $ready = $this->access->readiness($exam, $section, $keys);
        $cards = ReportCard::query()->where('exam_id', $exam->id)->whereIn('student_id', $keys)->get()->keyBy('student_id');

        return array_map(function ($row) use ($ready, $cards) {
            /** @var ReportCard|null $card */
            $card = $cards->get($row->studentKey);

            return new ReportCardRowData(
                student_ulid: $row->ulid,
                roll_number: $row->rollNumber,
                name: $row->name,
                marks: $ready[$row->studentKey],
                card: $card === null ? null : new ReportCardSummaryData(
                    ulid: $card->ulid,
                    kind: $card->kind,
                    status: $card->status,
                    prepared_on: (string) $this->schoolDay($card->prepared_at),
                    file_name: $card->file_name,
                    return_note: $card->status === ReportCardStatus::Returned ? $card->return_note : null,
                ),
            );
        }, $roster);
    }

    private function approvers(SectionRefData $section): ?string
    {
        $holders = $this->holders->over('reportcards.approve', $section->scopeTarget());
        $named = array_values(array_filter($holders, fn (array $holder) => ! $holder['is_owner']));
        $named = $named === [] ? $holders : $named;

        if ($named === []) {
            return null;
        }

        return implode(', ', array_map(fn (array $holder) => "{$holder['name']} (".strtolower($holder['role']).')', array_slice($named, 0, 2)));
    }

    private function examOf(SectionRefData $section, string $examUlid): Exam
    {
        foreach ($this->access->examsFor($section) as $exam) {
            if ($exam->ulid === $examUlid) {
                return $exam;
            }
        }

        throw ReportCardException::notFound();
    }

    /**
     * @param  list<SectionRefData>  $sections
     */
    private function pickSection(array $sections, ?string $ulid): ?SectionRefData
    {
        foreach ($sections as $section) {
            if ($section->ulid === $ulid) {
                return $section;
            }
        }

        return $sections[0] ?? null;
    }

    private function schoolDay(?DateTimeInterface $instant): ?string
    {
        if ($instant === null) {
            return null;
        }

        return CarbonImmutable::instance($instant)->setTimezone((string) config('shiksha.timezone', 'Asia/Kolkata'))->toDateString();
    }
}
