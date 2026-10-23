<?php

namespace App\Domain\Access\Data;

use App\Domain\Access\Enums\PermissionRisk;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PermissionOptionData extends Data
{
    public function __construct(
        public string $key,
        public string $module,
        public PermissionRisk $risk,
        public bool $is_owner_only,
        public string $description,
        public bool $held_by_you,
    ) {}
}
