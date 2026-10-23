<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * The Schools list: one page of rows plus the figures around it.
 */
final readonly class SchoolListData implements JsonSerializable
{
    /**
     * @param  list<SchoolRowData>  $rows
     * @param  array<string, int>  $statusCounts  every status plus "all", ignoring search and plan filters
     * @param  list<array{ulid: string, name: string}>  $plans
     */
    public function __construct(
        public array $rows,
        public int $page,
        public int $lastPage,
        public int $matching,
        public int $totalSchools,
        public int $totalStudents,
        public array $statusCounts,
        public array $plans,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'rows' => $this->rows,
            'page' => $this->page,
            'last_page' => $this->lastPage,
            'matching' => $this->matching,
            'total_schools' => $this->totalSchools,
            'total_students' => $this->totalStudents,
            'status_counts' => $this->statusCounts,
            'plans' => $this->plans,
        ];
    }
}
