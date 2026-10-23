<?php

namespace App\Domain\Students;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Who counts as a sibling: students who share a guardian, plus pairs staff
 * linked by hand (for families whose guardians differ). Ids only; callers
 * decide which siblings a viewer may see.
 */
class SiblingFinder
{
    /**
     * @return list<int>
     */
    public function idsFor(int $studentId): array
    {
        $db = $this->db();

        $shared = $db->table('guardian_student as a')
            ->join('guardian_student as b', 'b.guardian_id', '=', 'a.guardian_id')
            ->where('a.student_id', $studentId)
            ->where('b.student_id', '!=', $studentId)
            ->pluck('b.student_id');

        $linkedAsFirst = $db->table('student_sibling_links')->where('student_id', $studentId)->pluck('sibling_id');
        $linkedAsSecond = $db->table('student_sibling_links')->where('sibling_id', $studentId)->pluck('student_id');

        return array_values($shared->merge($linkedAsFirst)->merge($linkedAsSecond)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->all());
    }

    /**
     * Which of these students have at least one sibling.
     *
     * @param  list<int>  $studentIds
     * @return array<int, true>
     */
    public function withSiblings(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $db = $this->db();
        $found = [];

        $shared = $db->table('guardian_student as a')
            ->join('guardian_student as b', 'b.guardian_id', '=', 'a.guardian_id')
            ->whereIn('a.student_id', $studentIds)
            ->whereColumn('b.student_id', '!=', 'a.student_id')
            ->distinct()
            ->pluck('a.student_id');

        foreach ($shared as $id) {
            $found[(int) $id] = true;
        }

        foreach (['student_id', 'sibling_id'] as $column) {
            foreach ($db->table('student_sibling_links')->whereIn($column, $studentIds)->pluck($column) as $id) {
                $found[(int) $id] = true;
            }
        }

        return $found;
    }

    public function sharesGuardian(int $studentId, int $otherId): bool
    {
        return $this->db()->table('guardian_student as a')
            ->join('guardian_student as b', 'b.guardian_id', '=', 'a.guardian_id')
            ->where('a.student_id', $studentId)
            ->where('b.student_id', $otherId)
            ->exists();
    }

    public function isLinkedByStaff(int $studentId, int $otherId): bool
    {
        [$low, $high] = $this->ordered($studentId, $otherId);

        return $this->db()->table('student_sibling_links')->where('student_id', $low)->where('sibling_id', $high)->exists();
    }

    /**
     * The pair in storage order (the lower id first).
     *
     * @return array{0: int, 1: int}
     */
    public function ordered(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    private function db(): Connection
    {
        return DB::connection(config('multitenancy.tenant_database_connection_name'));
    }
}
