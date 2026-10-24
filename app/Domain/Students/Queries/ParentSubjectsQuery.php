<?php

namespace App\Domain\Students\Queries;

use App\Domain\School\Data\ParentSubjectData;
use App\Domain\School\Queries\StudentSubjectsQuery;
use App\Domain\Students\Data\GuardianChildRef;

class ParentSubjectsQuery
{
    public function __construct(private readonly StudentSubjectsQuery $subjects) {}

    /**
     * The subjects of the child's class in the viewed session. The child comes
     * from ParentPortalContextQuery, so it is one of the guardian's own and
     * `sectionKey` is the section it was enrolled in that session.
     *
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     * @return list<ParentSubjectData>
     */
    public function get(GuardianChildRef $child, array $session): array
    {
        return $this->subjects->get($child->studentKey, $child->sectionKey, $session['key'], $session['is_current'], $child->classTeacherName);
    }
}
