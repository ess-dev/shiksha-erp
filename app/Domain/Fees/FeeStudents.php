<?php

namespace App\Domain\Fees;

use Illuminate\Support\Facades\DB;

/**
 * Looks students up for the fee actions, straight from the table (the way
 * Exams reads sections). Server-side only: the result carries internal ids.
 */
class FeeStudents
{
    /**
     * @return array{id: int, ulid: string, name: string, admission_number: string}|null
     */
    public function find(string $ulid): ?array
    {
        $row = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')
            ->where('ulid', $ulid)->first(['id', 'ulid', 'first_name', 'last_name', 'admission_number']);

        return $row === null ? null : [
            'id' => (int) $row->id,
            'ulid' => (string) $row->ulid,
            'name' => trim($row->first_name.' '.$row->last_name),
            'admission_number' => (string) $row->admission_number,
        ];
    }

    /**
     * The primary guardian's name, or any guardian's when none is marked primary.
     */
    public function primaryGuardianName(int $studentId): ?string
    {
        $name = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('guardian_student as gs')
            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->where('gs.student_id', $studentId)
            ->orderByDesc('gs.is_primary')->orderBy('gs.id')
            ->value('g.name');

        return is_string($name) ? $name : null;
    }
}
