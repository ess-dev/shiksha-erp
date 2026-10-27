<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One staff member in the directory tab: name, roles held and whether they are on approved leave today. */
#[TypeScript]
class StaffDirectoryRowData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public array $roles,
        public ?string $class_label,
        public bool $is_active,
        public bool $on_leave_today,
    ) {}
}
