<?php

namespace App\Domain\Fees;

use Illuminate\Support\Facades\DB;

/**
 * The school's classes as the fee screens need them (read straight from the
 * table, the way Exams does). Server-side only: rows carry internal ids.
 */
class FeeClasses
{
    /**
     * @return list<array{id: int, ulid: string, name: string, level: int}>
     */
    public function all(): array
    {
        return array_values(DB::connection(config('multitenancy.tenant_database_connection_name'))->table('school_classes')
            ->orderBy('level')->orderBy('id')
            ->get(['id', 'ulid', 'name', 'level'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'ulid' => (string) $row->ulid, 'name' => (string) $row->name, 'level' => (int) $row->level])
            ->all());
    }

    /**
     * @return array{id: int, ulid: string, name: string, level: int}|null
     */
    public function find(string $ulid): ?array
    {
        foreach ($this->all() as $class) {
            if ($class['ulid'] === $ulid) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Currently enrolled students per class in an academic year.
     *
     * @return array<int, int> class id => students
     */
    public function enrolledCounts(int $yearId): array
    {
        $counts = [];

        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('enrollments as e')
            ->join('students as st', 'st.id', '=', 'e.student_id')
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->where('e.academic_year_id', $yearId)->where('e.is_current', true)->where('st.status', 'enrolled')
            ->selectRaw('s.school_class_id as class_id, count(*) as total')
            ->groupBy('s.school_class_id')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->class_id] = (int) $row->total;
        }

        return $counts;
    }
}
