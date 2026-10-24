<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether a student's marks for an exam are ready for a report card: some
 * subject has no marks, all are entered but some are not published yet, or
 * every subject is entered and published.
 */
#[TypeScript]
enum MarksReadiness: string
{
    case Missing = 'missing';
    case Unpublished = 'unpublished';
    case Ready = 'ready';
}
