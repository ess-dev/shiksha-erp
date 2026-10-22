<?php

namespace App\Domain\Access\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RoleSummaryData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $template_name,
        public ?int $template_version,
        public bool $is_owner,
        public array $permissions,
        public int $assignee_count,
        public bool $update_pending,
    ) {}
}
