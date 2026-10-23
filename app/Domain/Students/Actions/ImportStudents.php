<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Data\ImportedStudentData;
use App\Domain\Students\Data\ImportResultData;
use App\Domain\Students\Data\ImportRow;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Queries\StudentImportQuery;
use App\Domain\Students\StudentGuard;

class ImportStudents extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly StudentImportQuery $import,
        private readonly CreateStudent $createStudent,
    ) {}

    /**
     * Creates the students from the rows of a CSV that pass the checks; rows
     * with errors are skipped and counted. The file is checked again here, so
     * nothing depends on what the preview showed. Each student is created
     * through CreateStudent (needs `students.import` over its section), and
     * the whole file is one transaction: it is all or nothing. Fee billing
     * options wait for the Fees module.
     *
     * @param  list<list<string>>  $table  Heading row first
     */
    public function handle(Identity $actor, array $table, string $fileName): ImportResultData
    {
        $this->guard->assertHolds($actor, 'students.import');

        if (count($table) - 1 > StudentImportQuery::MAX_ROWS) {
            throw new StudentException('Up to 1,000 students per file. Split it into smaller files.');
        }

        $analysis = $this->import->analyse($actor, $table);

        if ($analysis->missingColumns !== []) {
            throw new StudentException('The file is missing columns: '.implode(', ', $analysis->missingColumns).'. Use the template headings.');
        }

        $ready = $analysis->readyRows();

        if ($ready === []) {
            throw new StudentException('No rows are ready to import. Fix the rows marked and choose the file again.');
        }

        return $this->transaction(function () use ($actor, $ready, $analysis, $fileName) {
            $imported = [];

            foreach ($ready as $row) {
                /** @var ImportRow $row */
                $student = $this->createStudent->handle($actor, $row->student ?? throw StudentException::notFound(), 'students.import', 'import');

                $imported[] = new ImportedStudentData($student->ulid, $student->fullName(), (string) $row->classLabel, $student->admission_number);
            }

            $families = count(array_filter($ready, fn (ImportRow $row) => $row->joinsExistingFamily));
            $skipped = count($analysis->rows) - count($ready);

            $this->audit(new AuditEntry('students.imported', 'student_import', null, null, [
                'file' => mb_substr($fileName, 0, 120),
                'created' => count($imported),
                'skipped' => $skipped,
                'families_linked' => $families,
            ]));

            return new ImportResultData(count($imported), $skipped, $families, $imported);
        });
    }
}
