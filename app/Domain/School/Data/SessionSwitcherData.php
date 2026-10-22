<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SessionSwitcherData extends Data
{
    /**
     * @param  list<SessionOptionData>  $sessions  Academic years, newest first
     * @param  string  $selected  The session being viewed (the current one unless another was chosen)
     */
    public function __construct(
        public array $sessions,
        public string $selected,
    ) {}
}
