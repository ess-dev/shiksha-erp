<?php

namespace App\Domain\Identity\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which view a signed-in person is in and which other one they may switch to
 * (shown only to someone who is both staff and a parent at this school).
 */
#[TypeScript]
class SchoolContextData extends Data
{
    public function __construct(
        public bool $is_parent_view,
        public bool $can_open_parent_view,
        public bool $can_open_staff_view,
    ) {}
}
