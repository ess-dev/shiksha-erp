<?php

namespace App\Domain\Students\Data;

/**
 * A child of the signed-in guardian, as the parent queries need it. Server
 * side only: it carries the internal student key that other modules' queries
 * take. Pages get {@see ParentChildData} instead.
 */
final readonly class GuardianChildRef
{
    public function __construct(
        public int $studentKey,
        public string $ulid,
        public string $fullName,
        public string $firstName,
        public string $relation,
        public string $sectionLabel,
        public string $campusName,
        public ?int $rollNumber,
        public ?string $classTeacherName,
        public int $classLevel,
    ) {}

    public function toData(): ParentChildData
    {
        return new ParentChildData(
            $this->ulid,
            $this->fullName,
            $this->firstName,
            $this->relation,
            $this->sectionLabel,
            $this->campusName,
            $this->rollNumber,
            $this->classTeacherName,
        );
    }
}
