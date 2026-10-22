<?php

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RoleUpdateData extends Data
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public function __construct(
        public string $role_ulid,
        public string $role_name,
        public ?int $from_version,
        public int $to_version,
        public array $added,
        public array $removed,
        public bool $adds_critical,
    ) {}
}
