<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\LeaveApprovalsData;
use App\Domain\Leave\Data\LeaveAttachmentData;
use App\Domain\Leave\Data\StaffLeaveItemData;
use App\Domain\Leave\Data\StaffLeaveScreenData;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\LeaveAccess;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\School\Data\SectionRefData;
use App\Domain\Shared\CalendarDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class StaffLeaveQuery
{
    /** At most this many requests are sent. */
    private const LIMIT = 200;

    /** illustrative — verify: how long a certificate link stays valid. */
    private const LINK_MINUTES = 10;

    public function __construct(
        private readonly LeaveAccess $access,
        private readonly LeaveRules $rules,
        private readonly StaffRefQuery $staff,
        private readonly PermissionScopesQuery $scopes,
    ) {}

    /**
     * The Student leave screen for someone holding `leave.approve` or
     * `leave.approve_long`: requests of the current year's sections they
     * cover. "review" lists what waits for them (soonest first), "all" lists
     * every request that was not cancelled (latest first).
     */
    public function screen(Identity $actor, string $tab): StaffLeaveScreenData
    {
        $tab = $tab === 'all' ? 'all' : 'review';
        $sections = $this->access->sections($actor);
        $items = $this->items($actor, $this->requests($sections, [LeaveStatus::Pending, LeaveStatus::Recommended, LeaveStatus::Approved, LeaveStatus::Rejected]), $sections);
        $review = array_values(array_filter($items, fn (StaffLeaveItemData $item) => $item->can_approve || $item->can_reject || $item->can_recommend));
        $today = CalendarDate::today();

        $shown = $tab === 'review' ? $review : $items;
        usort($shown, fn (StaffLeaveItemData $a, StaffLeaveItemData $b) => $tab === 'review' ? $a->from_on <=> $b->from_on : $b->from_on <=> $a->from_on);

        return new StaffLeaveScreenData(
            $tab,
            $shown,
            count($review),
            array_values(array_map(
                fn (StaffLeaveItemData $item) => $item->student_name,
                array_filter($items, fn (StaffLeaveItemData $item) => $item->status === LeaveStatus::Approved && $item->from_on <= $today && $item->to_on >= $today),
            )),
            $this->rules->escalationDays(),
            $this->scopes->get($actor, 'settings.manage')->allows(),
        );
    }

    /**
     * Long leave for the coordinator or principal: requests they may decide
     * that a class teacher recommended or that are longer than the limit and
     * still with the class teacher. Soonest first.
     */
    public function approvals(Identity $actor): LeaveApprovalsData
    {
        $sections = $this->access->sections($actor);
        $items = $this->items($actor, $this->requests($sections, [LeaveStatus::Pending, LeaveStatus::Recommended], onlyLong: true), $sections);
        $items = array_values(array_filter($items, fn (StaffLeaveItemData $item) => $item->can_approve));
        usort($items, fn (StaffLeaveItemData $a, StaffLeaveItemData $b) => $a->from_on <=> $b->from_on);

        return new LeaveApprovalsData($items, $this->rules->escalationDays());
    }

    /**
     * @param  array<int, SectionRefData>  $sections
     * @param  list<LeaveStatus>  $statuses
     * @return list<StudentLeaveRequest>
     */
    private function requests(array $sections, array $statuses, bool $onlyLong = false): array
    {
        if ($sections === []) {
            return [];
        }

        $query = StudentLeaveRequest::query()
            ->whereIn('section_id', array_keys($sections))
            ->whereIn('status', array_map(fn (LeaveStatus $status) => $status->value, $statuses))
            ->orderByDesc('from_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT);

        if ($onlyLong) {
            $query->where('needs_coordinator', true);
        }

        return array_values($query->get()->all());
    }

    /**
     * @param  list<StudentLeaveRequest>  $requests
     * @param  array<int, SectionRefData>  $sections
     * @return list<StaffLeaveItemData>
     */
    private function items(Identity $actor, array $requests, array $sections): array
    {
        if ($requests === []) {
            return [];
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $students = $connection->table('students')->whereIn('id', array_unique(array_map(fn ($leave) => $leave->student_id, $requests)))
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $rolls = $connection->table('enrollments')->whereIn('student_id', $students->keys()->all())->where('is_current', true)->pluck('roll_number', 'student_id');
        $guardians = $connection->table('guardians')->whereIn('id', array_unique(array_map(fn ($leave) => $leave->applied_by_guardian_id, $requests)))->pluck('name', 'id');
        $names = $this->staff->namesByKey(array_merge(
            array_map(fn ($leave) => $leave->recommended_by_staff_id, $requests),
            array_map(fn ($leave) => $leave->decided_by_staff_id, $requests),
        ));

        $items = [];

        foreach ($requests as $leave) {
            $section = $sections[$leave->section_id] ?? null;
            $student = $students->get($leave->student_id);

            if ($section === null || $student === null) {
                continue;
            }

            $can = $this->access->abilities($actor, $leave, $section);

            $items[] = new StaffLeaveItemData(
                $leave->ulid,
                trim("{$student->first_name} {$student->last_name}"),
                $section->label(),
                isset($rolls[$leave->student_id]) ? (int) $rolls[$leave->student_id] : null,
                $leave->status,
                $leave->needs_coordinator,
                $leave->reason_type->value,
                $leave->reason_type->label(),
                $leave->reason,
                $leave->from_on,
                $leave->to_on,
                $leave->school_days,
                (string) ($guardians[$leave->applied_by_guardian_id] ?? 'Parent'),
                $leave->applied_at->setTimezone((string) config('shiksha.timezone'))->format('Y-m-d'),
                $leave->attachment_name === null ? null : new LeaveAttachmentData(
                    $leave->attachment_name,
                    (string) $leave->attachment_mime,
                    (int) $leave->attachment_size,
                    URL::temporarySignedRoute('leave.attachment', now()->addMinutes(self::LINK_MINUTES), ['leave' => $leave->ulid]),
                    str_starts_with((string) $leave->attachment_mime, 'image/'),
                ),
                $leave->recommended_by_staff_id === null ? null : ($names[$leave->recommended_by_staff_id] ?? null),
                $leave->recommendation_note,
                $leave->decided_by_staff_id === null ? null : ($names[$leave->decided_by_staff_id] ?? null),
                $leave->decision_note,
                $can->approve,
                $can->reject,
                $can->recommend,
            );
        }

        return $items;
    }
}
