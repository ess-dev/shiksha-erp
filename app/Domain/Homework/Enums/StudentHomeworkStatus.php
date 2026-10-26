<?php

namespace App\Domain\Homework\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a student stands on one assignment. "Late" means uploaded after the due
 * date; notebook work the teacher checked counts as submitted; "missing" is a
 * due date that passed with nothing handed in.
 */
#[TypeScript]
enum StudentHomeworkStatus: string
{
    case NotYet = 'not_yet';
    case Submitted = 'submitted';
    case Late = 'late';
    case Missing = 'missing';
}
