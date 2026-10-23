<?php

namespace App\Domain\Access\Data;

use App\Domain\Access\Enums\ScopeType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AssignmentData extends Data
{
    /**
     * @param  list<string>  $campus_ulids
     * @param  list<string>  $section_ulids
     */
    public function __construct(
        public string $ulid,
        public string $role_ulid,
        public string $role_name,
        public bool $is_owner_role,
        public ScopeType $scope_type,
        public array $campus_ulids,
        public array $section_ulids,
        public ?int $from_level,
        public ?int $to_level,
    ) {}
}
