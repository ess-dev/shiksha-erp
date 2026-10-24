<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Sent for approval, sent back (or withdrawn) to be prepared again, or shown
 * to parents. Published is final.
 */
#[TypeScript]
enum ReportCardStatus: string
{
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Published = 'published';
}
