<?php

namespace App\Domain\Homework\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A child's homework: still to do, handed in (or checked), or past its due date with nothing handed in. */
#[TypeScript]
enum ParentHomeworkState: string
{
    case Due = 'due';
    case Done = 'done';
    case Missed = 'missed';
}
