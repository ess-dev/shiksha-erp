<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Data\StudentFilters;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Queries\StudentsQuery;
use App\Domain\Students\StudentGuard;

class ExportStudents extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly StudentsQuery $students,
    ) {}

    /**
     * Builds the Students list as CSV rows (with full guardian phone numbers)
     * and records that it was exported. Needs `students.export`; only students
     * within that permission's scope are included.
     *
     * @return list<list<string>> the header row first
     */
    public function handle(Identity $actor, StudentFilters $filters): array
    {
        $this->guard->assertHolds($actor, 'students.export');

        return $this->transaction(function () use ($actor, $filters) {
            $rows = $this->students->exportRows($actor, $filters) ?? throw StudentException::notAllowed();

            $this->audit(new AuditEntry('students.exported', 'student_list', null, null, [
                'students' => count($rows) - 1,
                'status' => $filters->status,
                'section' => $filters->sectionUlid,
                'campus' => $filters->campusUlid,
                'has_search' => trim($filters->search) !== '',
            ]));

            return $rows;
        });
    }
}
