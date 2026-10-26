<?php

namespace App\Domain\Homework\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Done in the notebook and checked in class, or uploaded by the parent in the app. */
#[TypeScript]
enum SubmissionType: string
{
    case Notebook = 'notebook';
    case Upload = 'upload';
}
