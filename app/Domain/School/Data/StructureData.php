<?php

namespace App\Domain\School\Data;

use JsonSerializable;

/**
 * Shapes sent to pages for the school structure. They carry ulids and the
 * fields a screen needs, never internal ids.
 */
final readonly class StructureData implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $fields
     */
    private function __construct(public string $type, public string $ulid, public array $fields) {}

    public static function campus(string $ulid, string $name, string $code, string $status): self
    {
        return new self('campus', $ulid, compact('name', 'code', 'status'));
    }

    public static function academicYear(string $ulid, string $name, string $startsOn, string $endsOn, bool $isCurrent): self
    {
        return new self('academic_year', $ulid, [
            'name' => $name, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'is_current' => $isCurrent,
        ]);
    }

    public static function schoolClass(string $ulid, string $name, int $level): self
    {
        return new self('school_class', $ulid, compact('name', 'level'));
    }

    public static function subject(string $ulid, string $name, string $code, string $status): self
    {
        return new self('subject', $ulid, compact('name', 'code', 'status'));
    }

    public static function section(
        string $ulid,
        string $name,
        ?int $capacity,
        string $status,
        string $academicYearUlid,
        string $campusUlid,
        string $campusName,
        string $classUlid,
        string $className,
        int $classLevel,
    ): self {
        return new self('section', $ulid, [
            'name' => $name,
            'capacity' => $capacity,
            'status' => $status,
            'academic_year_ulid' => $academicYearUlid,
            'campus' => ['ulid' => $campusUlid, 'name' => $campusName],
            'class' => ['ulid' => $classUlid, 'name' => $className, 'level' => $classLevel],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['ulid' => $this->ulid, ...$this->fields];
    }
}
