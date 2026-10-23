<?php

namespace App\Domain\Access\Exceptions;

use DomainException;

/**
 * A role or assignment change the access rules forbid.
 */
class GuardrailViolation extends DomainException
{
    public static function ownerOnlyPermission(string $permission): self
    {
        return new self("Permission [{$permission}] can only belong to an owner role.");
    }

    public static function permissionNotHeld(string $permission): self
    {
        return new self("You cannot grant [{$permission}] because you do not hold it.");
    }

    public static function scopeNotHeld(string $permission): self
    {
        return new self("You cannot grant [{$permission}] over a scope wider than your own.");
    }

    public static function unknownPermission(string $permission): self
    {
        return new self("Permission [{$permission}] is not available to this school.");
    }

    public static function criticalNeedsConfirmation(): self
    {
        return new self('Granting a critical permission needs explicit confirmation.');
    }

    public static function lastOwner(): self
    {
        return new self('A school must keep at least one owner.');
    }

    public static function notAuthorised(): self
    {
        return new self('You are not allowed to manage roles in this school.');
    }
}
