<?php

namespace App\Domain\Students\Queries;

use App\Domain\Attendance\Queries\StudentAttendanceQuery;
use App\Domain\Exams\Data\StudentResultsData;
use App\Domain\Exams\Queries\StudentResultsQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Data\ReportCardData;
use App\Domain\Students\Exceptions\StudentException;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The child's results and report card in the parent portal. The child comes
 * from ParentPortalContextQuery, so it is one of the guardian's own and
 * `sectionKey` is the section it was enrolled in that session.
 */
class ParentResultsQuery
{
    public function __construct(
        private readonly StudentResultsQuery $results,
        private readonly StudentAttendanceQuery $attendance,
    ) {}

    /**
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     */
    public function get(GuardianChildRef $child, array $session): StudentResultsData
    {
        return $this->results->get($child->studentKey, $child->sectionKey, $session['key'], $session['is_current']);
    }

    public function admissionNumber(GuardianChildRef $child): string
    {
        return (string) DB::connection(config('multitenancy.tenant_database_connection_name'))
            ->table('students')->where('id', $child->studentKey)->value('admission_number');
    }

    /**
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     *
     * @throws StudentException 404 when the exam is not among the child's results
     */
    public function reportCard(GuardianChildRef $child, array $session, string $examUlid): ReportCardData
    {
        $result = $this->results->forExam($child->studentKey, $child->sectionKey, $session['key'], $session['is_current'], $examUlid);

        if ($result === null || $result->total_max === null) {
            throw StudentException::notFound();
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $student = $connection->table('students')->where('id', $child->studentKey)->first(['admission_number', 'born_on']);
        $campusAddress = $connection->table('sections')->join('campuses', 'campuses.id', '=', 'sections.campus_id')
            ->where('sections.id', $child->sectionKey)->value('campuses.address');
        $examEndsOn = (string) $connection->table('exams')->where('ulid', $examUlid)->value('ends_on');

        if ($student === null) {
            throw StudentException::notFound();
        }

        $tenant = Tenant::current();
        $attendanceTo = min($examEndsOn, $session['ends_on'], CalendarDate::today());

        return new ReportCardData(
            school_name: (string) $tenant?->name,
            board: $tenant?->board,
            campus_name: $child->campusName,
            campus_address: is_string($campusAddress) && $campusAddress !== '' ? $campusAddress : null,
            session_name: $session['name'],
            student_name: $child->fullName,
            admission_number: (string) $student->admission_number,
            born_on: (string) $student->born_on,
            class_label: $child->sectionLabel,
            class_teacher_name: $child->classTeacherName,
            attendance: $tenant?->hasModule('attendance') && $attendanceTo >= $session['starts_on']
                ? $this->attendance->tally($child->studentKey, $session['starts_on'], $attendanceTo)
                : null,
            result: $result,
        );
    }
}
