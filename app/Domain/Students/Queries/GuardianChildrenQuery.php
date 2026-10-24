<?php

namespace App\Domain\Students\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;

/**
 * The children a signed-in guardian may see: only those the school linked to
 * the guardian with portal access, in the academic session being viewed. In
 * the current session only children still enrolled appear.
 */
class GuardianChildrenQuery
{
    public function __construct(private readonly SectionDirectoryQuery $sections) {}

    public function guardian(Identity $identity): ?Guardian
    {
        return Guardian::query()->where('identity_ulid', $identity->ulid)->first();
    }

    /**
     * @param  int  $yearKey  The academic year (session) to look at
     * @return list<GuardianChildRef> Highest class first, as in the prototype
     */
    public function forYear(Identity $identity, int $yearKey, bool $isCurrentYear): array
    {
        $guardian = $this->guardian($identity);

        if ($guardian === null) {
            return [];
        }

        $query = Student::query()
            ->join('guardian_student', 'guardian_student.student_id', '=', 'students.id')
            ->join('enrollments', function ($join) use ($yearKey) {
                $join->on('enrollments.student_id', '=', 'students.id')->where('enrollments.academic_year_id', '=', $yearKey);
            })
            ->where('guardian_student.guardian_id', $guardian->id)
            ->where('guardian_student.has_portal_access', true)
            ->select(['students.id', 'students.ulid', 'students.first_name', 'students.last_name', 'guardian_student.relation', 'enrollments.section_id', 'enrollments.roll_number', 'enrollments.is_current']);

        if ($isCurrentYear) {
            $query->where('students.status', StudentStatus::Enrolled->value)->where('enrollments.is_current', true);
        } else {
            // The last enrolment of that year, when a section change gave several.
            $query->whereRaw('enrollments.id = (select max(e2.id) from enrollments e2 where e2.student_id = students.id and e2.academic_year_id = ?)', [$yearKey]);
        }

        $rows = $query->get();
        $sections = $this->sections->byKeys(array_values($rows->map(fn ($row) => (int) $row->getAttribute('section_id'))->all()));

        $children = [];

        foreach ($rows as $row) {
            $section = $sections[(int) $row->getAttribute('section_id')] ?? null;

            if ($section === null) {
                continue;
            }

            $first = (string) $row->getAttribute('first_name');

            $children[] = new GuardianChildRef(
                (int) $row->getAttribute('id'),
                (string) $row->getAttribute('ulid'),
                trim($first.' '.$row->getAttribute('last_name')),
                $first,
                (string) $row->getAttribute('relation'),
                $section->label(),
                $section->campusName,
                $row->getAttribute('roll_number') === null ? null : (int) $row->getAttribute('roll_number'),
                $section->classTeacherName,
                $section->classLevel,
                $section->sectionKey,
            );
        }

        usort($children, fn (GuardianChildRef $a, GuardianChildRef $b) => [$b->classLevel, $a->fullName] <=> [$a->classLevel, $b->fullName]);

        return $children;
    }
}
