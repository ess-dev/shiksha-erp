<?php

namespace App\Domain\Students\Enums;

/**
 * Where a guardian stands with the parent app (web portal or mobile). "Active"
 * means the guardian accepted the invite through either; parent sign-in is not
 * built yet, so nobody reaches it today.
 */
enum ParentAppStatus: string
{
    case NotInvited = 'not_invited';
    case Invited = 'invited';
    case Active = 'active';
}
