<?php

namespace App\Domain\Students\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\SiblingData;
use App\Domain\Students\Enums\SiblingVia;
use App\Domain\Students\Models\Student;
use App\Domain\Students\SiblingFinder;
use App\Domain\Students\StudentVisibility;
use Illuminate\Database\Eloquent\Builder;

class SiblingCandidatesQuery
{
    private const LIMIT = 8;

    public function __construct(
        private readonly StudentVisibility $visibility,
        private readonly SiblingFinder $siblings,
    ) {}

    /**
     * Current students the identity may link as siblings of this one, found by
     * name or admission number (at least two characters). Excludes the student
     * and anyone already a sibling. Uses the `students.link_siblings` scope, so
     * nobody outside it can be found.
     *
     * @return list<SiblingData>
     */
    public function search(Identity $identity, Student $student, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $query = $this->visibility->query($identity, 'students.link_siblings');

        if ($query === null) {
            return [];
        }

        $like = '%'.mb_strtolower(addcslashes($term, '%_\\')).'%';

        return array_values($query
            ->where('students.id', '!=', $student->id)
            ->whereNotIn('students.id', $this->siblings->idsFor($student->id))
            ->where('students.status', 'enrolled')
            ->where(function (Builder $where) use ($like) {
                $where->whereRaw("lower(students.first_name || ' ' || students.last_name) like ?", [$like])
                    ->orWhereRaw('lower(students.admission_number) like ?', [$like]);
            })
            ->addSelect(['sc.name as class_name', 's.name as section_name', 'c.name as campus_name'])
            ->orderBy('students.first_name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Student $found) => new SiblingData(
                ulid: $found->ulid,
                name: $found->fullName().' · '.$found->admission_number,
                class_label: $found->getAttribute('class_name').'-'.$found->getAttribute('section_name'),
                campus_name: (string) $found->getAttribute('campus_name'),
                via: SiblingVia::Staff,
            ))
            ->all());
    }
}
