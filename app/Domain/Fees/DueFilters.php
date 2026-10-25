<?php

namespace App\Domain\Fees;

/**
 * What the Dues screen is filtered and sorted by. `status` is one of open,
 * overdue, due, partial, upcoming; `sort` one of due, balance, class.
 */
final readonly class DueFilters
{
    public function __construct(
        public string $search = '',
        public ?string $sectionUlid = null,
        public ?string $installmentUlid = null,
        public string $status = 'open',
        public string $sort = 'due',
        public ?string $campusUlid = null,
    ) {}
}
