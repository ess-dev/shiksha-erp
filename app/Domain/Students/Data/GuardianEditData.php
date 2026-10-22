<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GuardianEditData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $relation,
        public string $phone,
        public string $email,
        public string $occupation,
        public bool $is_primary,
        public bool $has_portal_access,
    ) {}
}
