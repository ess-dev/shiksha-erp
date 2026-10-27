<?php

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StaffRoleChipData extends Data
{
    public function __construct(
        public string $role_name,
        public string $scope_label,
    ) {}
}
