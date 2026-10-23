<?php

namespace App\Domain\Attendance\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the strip above the register says (the page writes the sentence).
 */
#[TypeScript]
enum RegisterState: string
{
    /** Not a school day (weekly day off or holiday): no register. */
    case Closed = 'closed';

    /** The person can look but not change: the class teacher marks it. */
    case ViewOnly = 'view_only';

    /** The class teacher's window has passed; a corrector must change it. */
    case Locked = 'locked';

    /** Saved already and the person may still change it. */
    case Saved = 'saved';

    /** Today, not marked yet. */
    case Pending = 'pending';

    /** An earlier day that was never marked; saving records it late. */
    case Missed = 'missed';
}
