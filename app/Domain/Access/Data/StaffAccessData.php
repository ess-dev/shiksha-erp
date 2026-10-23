<?php

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StaffAccessData extends Data
{
    /**
     * @param  list<AssignmentData>  $assignments
     */
    public function __construct(
        public string $ulid,
        public string $full_name,
        public string $status,
        public array $assignments,
    ) {}
}
