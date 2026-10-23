<?php

namespace App\Domain\Access\Data;

/**
 * Where a record lives, for scope checks: its campus, the class level of its
 * section, and the section itself. Fields that do not apply stay null.
 * (Campus and section tables arrive with the school-structure module.)
 */
final readonly class ScopeTarget
{
    public function __construct(
        public ?string $campusUlid = null,
        public ?int $classLevel = null,
        public ?string $sectionUlid = null,
    ) {}
}
