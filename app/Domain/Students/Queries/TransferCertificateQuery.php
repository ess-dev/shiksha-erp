<?php

namespace App\Domain\Students\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\TransferCertificateData;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Student;
use App\Domain\Students\StudentGuard;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class TransferCertificateQuery
{
    public function __construct(private readonly StudentGuard $guard) {}

    /**
     * What the printable transfer certificate shows. Needs `students.issue_tc`
     * over the student, who must already hold a TC.
     */
    public function get(Identity $identity, Student $student): TransferCertificateData
    {
        $this->guard->assertMayOn($identity, 'students.issue_tc', $student);

        if ($student->status !== StudentStatus::Transferred || $student->tc_number === null) {
            throw StudentException::notFound();
        }

        $db = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $last = $db->table('enrollments as e')
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->where('e.student_id', $student->id)
            ->orderByDesc('e.id')
            ->first(['sc.name as class_name', 's.name as section_name']);

        $guardians = $db->table('guardian_student as gs')
            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->where('gs.student_id', $student->id)
            ->orderByDesc('gs.is_primary')
            ->pluck('g.name')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();

        return new TransferCertificateData(
            school_name: Tenant::current()->name ?? '',
            tc_number: $student->tc_number,
            student_name: $student->fullName(),
            admission_number: $student->admission_number,
            born_on: $student->born_on,
            gender: $student->gender->label(),
            class_label: $last === null ? null : $last->class_name.'-'.$last->section_name,
            admitted_on: $student->admitted_on,
            last_day: (string) $student->left_on,
            reason: (string) $student->leave_reason,
            guardian_names: array_values($guardians),
        );
    }
}
