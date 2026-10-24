<?php

namespace App\Domain\Students\Queries;

use App\Domain\Attendance\Queries\StudentAttendanceQuery;
use App\Domain\Exams\Queries\StudentResultsQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\ReportCardData;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * A report card for one student and one exam, built from **published** marks:
 * the school, the student's section that session (class, campus and its class
 * teacher), attendance up to the exam's last day, and the result. Used for the
 * parent's "Open as report card" and for the snapshot a class teacher sends.
 * Callers check who may see the student.
 */
class ReportCardQuery
{
    public function __construct(
        private readonly StudentResultsQuery $results,
        private readonly StudentAttendanceQuery $attendance,
    ) {}

    /**
     * Null when the exam is not among the student's results or nothing of it is published.
     *
     * @param  int  $sectionKey  The section the student was enrolled in that session
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     */
    public function build(int $studentKey, int $sectionKey, array $session, string $examUlid, ?string $remark = null): ?ReportCardData
    {
        $result = $this->results->forExam($studentKey, $sectionKey, $session['key'], $session['is_current'], $examUlid);

        if ($result === null || $result->total_max === null) {
            return null;
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $student = $connection->table('students')->where('id', $studentKey)->first(['first_name', 'last_name', 'admission_number', 'born_on']);
        $section = $connection->table('sections')
            ->join('campuses', 'campuses.id', '=', 'sections.campus_id')
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->leftJoin('staff', 'staff.id', '=', 'sections.class_teacher_staff_id')
            ->where('sections.id', $sectionKey)
            ->first(['sections.name as section_name', 'school_classes.name as class_name', 'campuses.name as campus_name', 'campuses.address', 'staff.full_name as class_teacher']);

        if ($student === null || $section === null) {
            return null;
        }

        $examEndsOn = (string) $connection->table('exams')->where('ulid', $examUlid)->value('ends_on');
        $tenant = Tenant::current();
        $attendanceTo = min($examEndsOn, $session['ends_on'], CalendarDate::today());
        $remark = $remark === null ? null : trim($remark);

        return new ReportCardData(
            school_name: (string) $tenant?->name,
            board: $tenant?->board,
            campus_name: (string) $section->campus_name,
            campus_address: is_string($section->address) && $section->address !== '' ? $section->address : null,
            session_name: $session['name'],
            student_name: trim("{$student->first_name} {$student->last_name}"),
            admission_number: (string) $student->admission_number,
            born_on: (string) $student->born_on,
            class_label: "{$section->class_name}-{$section->section_name}",
            class_teacher_name: $section->class_teacher === null ? null : (string) $section->class_teacher,
            attendance: $tenant?->hasModule('attendance') && $attendanceTo >= $session['starts_on']
                ? $this->attendance->tally($studentKey, $session['starts_on'], $attendanceTo)
                : null,
            result: $result,
            remark: $remark === '' ? null : $remark,
        );
    }
}
