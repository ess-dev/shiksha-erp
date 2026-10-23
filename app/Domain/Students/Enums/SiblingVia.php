<?php

namespace App\Domain\Students\Enums;

/**
 * How two students count as siblings: they share a guardian, or staff linked them.
 */
enum SiblingVia: string
{
    case Guardian = 'guardian';
    case Staff = 'staff';
}
