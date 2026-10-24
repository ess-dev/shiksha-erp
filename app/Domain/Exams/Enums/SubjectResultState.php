<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A subject on a child's results: marks published, absent, published without
 * marks for the child, or still awaiting the teacher's publish.
 */
#[TypeScript]
enum SubjectResultState: string
{
    case Published = 'published';
    case Absent = 'absent';
    case NotEntered = 'not_entered';
    case Awaiting = 'awaiting';
}
