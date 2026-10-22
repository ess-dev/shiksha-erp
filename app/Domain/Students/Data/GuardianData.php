<?php

namespace App\Domain\Students\Data;

use App\Domain\Students\Enums\ParentAppStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GuardianData extends Data
{
    /**
     * @param  list<string>  $other_children  Names of other children this guardian is linked to, within the viewer's reach
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $relation,
        public bool $is_primary,
        public string $phone,
        public ?string $email,
        public ?string $occupation,
        public bool $has_portal_access,
        public ParentAppStatus $parent_app,
        public array $other_children,
        public int $hidden_other_children,
    ) {}
}
