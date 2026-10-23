<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * A plan as the Plans screen shows it. Money stays in paise.
 */
final readonly class PlanData implements JsonSerializable
{
    /**
     * @param  list<string>  $moduleKeys
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $description,
        public ?string $badge,
        public int $pricePerStudentYearPaise,
        public int $minStudents,
        public array $moduleKeys,
        public int $schoolsCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'description' => $this->description,
            'badge' => $this->badge,
            'price_per_student_year_paise' => $this->pricePerStudentYearPaise,
            'min_students' => $this->minStudents,
            'module_keys' => $this->moduleKeys,
            'schools_count' => $this->schoolsCount,
        ];
    }
}
