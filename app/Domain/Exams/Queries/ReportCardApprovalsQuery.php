<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Exams\Data\ApprovalCardData;
use App\Domain\Exams\Data\ApprovalGroupData;
use App\Domain\Exams\Data\MarksSectionOptionData;
use App\Domain\Exams\Data\ReportCardApprovalsData;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\ReportCardAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\Shared\CalendarDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Report cards waiting for (or already past) the approver's decision, for the
 * Approvals screen and the approver's view of Report cards. Only classes the
 * person's `reportcards.approve` covers appear; the section filter narrows
 * the list and an unknown section is ignored.
 */
class ReportCardApprovalsQuery
{
    public function __construct(
        private readonly ReportCardAccess $access,
        private readonly RosterQuery $roster,
    ) {}

    public function get(Identity $actor, ?ReportCardStatus $status = ReportCardStatus::Submitted, ?string $sectionUlid = null): ReportCardApprovalsData
    {
        $sections = $this->access->approverSections($actor);
        $byKey = [];

        foreach ($sections as $section) {
            $byKey[$section->sectionKey] = $section;
        }

        $sectionUlid = in_array($sectionUlid, array_column($sections, 'ulid'), true) ? $sectionUlid : null;
        $cards = $byKey === []
            ? collect()
            : ReportCard::query()->with('exam')->whereIn('section_id', array_keys($byKey))->orderBy('id')->get();

        $count = fn (ReportCardStatus $of): int => $cards->where('status', $of)->count();

        $shown = $cards
            ->filter(fn (ReportCard $card) => $status === null || $card->status === $status)
            ->filter(fn (ReportCard $card) => $sectionUlid === null || $byKey[$card->section_id]->ulid === $sectionUlid);

        $students = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')
            ->whereIn('id', $shown->pluck('student_id')->unique()->all())
            ->get(['id', 'ulid', 'first_name', 'last_name', 'admission_number'])
            ->keyBy('id');

        $groups = [];

        foreach ($shown->groupBy(fn (ReportCard $card) => $card->section_id.'|'.$card->exam_id) as $group) {
            /** @var ReportCard $first */
            $first = $group->first();
            $section = $byKey[$first->section_id];
            $roster = $this->roster->for($section->sectionKey, CalendarDate::today());
            $rolls = [];

            foreach ($roster as $row) {
                $rolls[$row->studentKey] = $row->rollNumber;
            }

            $all = $cards->where('section_id', $first->section_id)->where('exam_id', $first->exam_id);
            $items = $group->map(function (ReportCard $card) use ($students, $rolls) {
                $student = $students->get($card->student_id);

                return new ApprovalCardData(
                    ulid: $card->ulid,
                    student_ulid: (string) $student?->ulid,
                    student_name: trim("{$student?->first_name} {$student?->last_name}"),
                    admission_number: (string) $student?->admission_number,
                    roll_number: $rolls[$card->student_id] ?? null,
                    kind: $card->kind,
                    status: $card->status,
                    prepared_on: CarbonImmutable::instance($card->prepared_at)->setTimezone((string) config('shiksha.timezone', 'Asia/Kolkata'))->toDateString(),
                    file_name: $card->file_name,
                    return_note: $card->status === ReportCardStatus::Returned ? $card->return_note : null,
                );
            })->all();

            usort($items, fn (ApprovalCardData $a, ApprovalCardData $b) => [$a->roll_number ?? PHP_INT_MAX, $a->student_name] <=> [$b->roll_number ?? PHP_INT_MAX, $b->student_name]);

            $groups[] = new ApprovalGroupData(
                section_ulid: $section->ulid,
                section_label: $section->label(),
                campus_name: $section->campusName,
                exam_ulid: $first->exam->ulid,
                exam_name: $first->exam->name,
                class_teacher_name: $section->classTeacherName,
                awaiting_count: $all->where('status', ReportCardStatus::Submitted)->count(),
                published_count: $all->where('status', ReportCardStatus::Published)->count(),
                student_count: count($roster),
                cards: $items,
            );
        }

        usort($groups, fn (ApprovalGroupData $a, ApprovalGroupData $b) => [$a->section_label, $a->campus_name, $a->exam_name] <=> [$b->section_label, $b->campus_name, $b->exam_name]);

        return new ReportCardApprovalsData(
            status: $status,
            sections: array_map(fn (SectionRefData $s) => new MarksSectionOptionData($s->ulid, $s->label(), $s->campusName), $sections),
            section_ulid: $sectionUlid,
            awaiting_count: $count(ReportCardStatus::Submitted),
            published_count: $count(ReportCardStatus::Published),
            sent_back_count: $count(ReportCardStatus::Returned),
            groups: $groups,
        );
    }
}
