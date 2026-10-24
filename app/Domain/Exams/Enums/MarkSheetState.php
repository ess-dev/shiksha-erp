<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the person can do with a marks sheet right now: enter marks, only view
 * them (another teacher's subject), or nothing because they are published or
 * marking is closed.
 */
#[TypeScript]
enum MarkSheetState: string
{
    case Editable = 'editable';
    case ViewOnly = 'view_only';
    case Published = 'published';
    case Closed = 'closed';
}
