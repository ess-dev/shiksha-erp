<?php

namespace App\Domain\School\Data;

use App\Domain\Access\Data\ScopeTarget;

/**
 * A section as other modules need it: where it sits, its capacity and how many
 * students are currently enrolled. Server-side only, never sent to a page
 * (it carries the internal ids that foreign keys need).
 */
final readonly class SectionRefData
{
    public function __construct(
        public int $sectionKey,
        public string $ulid,
        public string $name,
        public ?int $capacity,
        public int $enrolledCount,
        public int $academicYearKey,
        public string $academicYearUlid,
        public string $campusUlid,
        public string $campusName,
        public string $classUlid,
        public string $className,
        public int $classLevel,
        public string $status,
    ) {}

    /** "Class 1-A", as the prototype writes it. */
    public function label(): string
    {
        return "{$this->className}-{$this->name}";
    }

    public function scopeTarget(): ScopeTarget
    {
        return new ScopeTarget($this->campusUlid, $this->classLevel, $this->ulid);
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->enrolledCount >= $this->capacity;
    }
}
