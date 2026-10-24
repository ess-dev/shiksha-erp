<?php

namespace App\Domain\Exams\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Generated from published marks, or a scan the class teacher uploaded. */
#[TypeScript]
enum ReportCardKind: string
{
    case Generated = 'generated';
    case File = 'file';
}
