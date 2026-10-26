<?php

namespace App\Domain\Leave\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Leave\Data\LeaveFormData;
use App\Domain\Leave\Data\LeaveReasonOptionData;
use App\Domain\Leave\Data\ParentLeaveItemData;
use App\Domain\Leave\Enums\LeaveReason;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\LeaveAttachmentStore;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\School\Queries\AttendanceCalendarQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\GuardianChildRef;

class ParentLeaveQuery
{
    /** At most this many requests are sent, newest first. */
    private const LIMIT = 100;

    public function __construct(
        private readonly StaffRefQuery $staff,
        private readonly LeaveRules $rules,
        private readonly AttendanceCalendarQuery $calendar,
    ) {}

    /**
     * The child's leave requests in the viewed session, newest first. Only the
     * current session lets a parent cancel.
     *
     * @param  array{key: int, is_current: bool}  $session
     * @return list<ParentLeaveItemData>
     */
    public function get(GuardianChildRef $child, array $session): array
    {
        $requests = StudentLeaveRequest::query()
            ->where('student_id', $child->studentKey)
            ->where('academic_year_id', $session['key'])
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $names = $this->staff->namesByKey(array_values($requests->flatMap(fn (StudentLeaveRequest $leave) => [$leave->recommended_by_staff_id, $leave->decided_by_staff_id])->filter()->all()));
        $items = [];

        foreach ($requests as $leave) {
            $items[] = new ParentLeaveItemData(
                $leave->ulid,
                $leave->from_on,
                $leave->to_on,
                $leave->school_days,
                $leave->reason_type->label(),
                $leave->reason,
                $leave->status,
                $leave->needs_coordinator,
                $leave->applied_at->toIso8601String(),
                $leave->recommended_by_staff_id === null ? null : ($names[$leave->recommended_by_staff_id] ?? null),
                $leave->decided_by_staff_id === null ? null : ($names[$leave->decided_by_staff_id] ?? null),
                $leave->status === LeaveStatus::Recommended ? $leave->recommendation_note : $leave->decision_note,
                $leave->attachment_name,
                $session['is_current'] && $leave->status->isOpen(),
            );
        }

        return $items;
    }

    /**
     * What the form needs to count school days and explain the rules.
     */
    public function form(GuardianChildRef $child): LeaveFormData
    {
        $calendar = $this->calendar->get();
        $min = $this->rules->earliestFrom();

        return new LeaveFormData(
            CalendarDate::today(),
            $min,
            LeaveRules::MAX_SPAN_DAYS,
            $this->rules->escalationDays(),
            LeaveRules::REASON_MIN,
            LeaveRules::REASON_MAX,
            (int) (LeaveAttachmentStore::MAX_BYTES / 1024 / 1024),
            $child->classTeacherName,
            $calendar->working_days,
            array_values(array_filter(array_map(fn ($holiday) => $holiday->holiday_on, $calendar->holidays), fn (string $date) => $date >= $min)),
            array_map(fn (LeaveReason $reason) => new LeaveReasonOptionData($reason->value, $reason->label()), LeaveReason::cases()),
        );
    }
}
