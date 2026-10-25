<?php

namespace App\Domain\Fees\Enums;

/**
 * Standard concessions are assigned to students by staff (later); the sibling
 * concession is applied automatically to the second child onward.
 */
enum ConcessionKind: string
{
    case Standard = 'standard';
    case Sibling = 'sibling';
}
