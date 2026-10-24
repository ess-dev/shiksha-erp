<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Exams\Data\SchoolReportCardData;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Models\ReportCard;
use Carbon\CarbonImmutable;

/**
 * A student's report cards from the class teacher in one academic session.
 * Parents only ever see published ones. Callers check who may see the student.
 */
class StudentReportCardsQuery
{
    public function __construct(private readonly StaffRefQuery $staff) {}

    /**
     * Published report cards, earliest exam first.
     *
     * @param  callable(ReportCard): string  $viewUrl
     * @return list<SchoolReportCardData>
     */
    public function published(int $studentKey, int $yearKey, callable $viewUrl): array
    {
        $cards = ReportCard::query()
            ->with('exam')
            ->join('exams', 'exams.id', '=', 'report_cards.exam_id')
            ->where('report_cards.student_id', $studentKey)
            ->where('exams.academic_year_id', $yearKey)
            ->where('report_cards.status', ReportCardStatus::Published->value)
            ->orderBy('exams.starts_on')
            ->select('report_cards.*')
            ->get();

        $names = $this->staff->namesByKey(array_values(array_map(intval(...), $cards->pluck('prepared_by_staff_id')->filter()->all())));
        $zone = (string) config('shiksha.timezone', 'Asia/Kolkata');

        return array_values($cards->map(fn (ReportCard $card) => new SchoolReportCardData(
            ulid: $card->ulid,
            exam_name: $card->exam->name,
            kind: $card->kind,
            prepared_by: $card->prepared_by_staff_id === null ? 'the class teacher' : ($names[$card->prepared_by_staff_id] ?? 'the class teacher'),
            prepared_on: CarbonImmutable::instance($card->prepared_at)->setTimezone($zone)->toDateString(),
            view_url: $viewUrl($card),
        ))->all());
    }

    /** Whether a report card for the student waits for approval this session. */
    public function hasWaiting(int $studentKey, int $yearKey): bool
    {
        return ReportCard::query()
            ->join('exams', 'exams.id', '=', 'report_cards.exam_id')
            ->where('report_cards.student_id', $studentKey)
            ->where('exams.academic_year_id', $yearKey)
            ->where('report_cards.status', ReportCardStatus::Submitted->value)
            ->exists();
    }

    /** One of the student's published report cards, or null. */
    public function findPublished(string $ulid, int $studentKey): ?ReportCard
    {
        return ReportCard::query()
            ->where('ulid', $ulid)
            ->where('student_id', $studentKey)
            ->where('status', ReportCardStatus::Published->value)
            ->first();
    }
}
