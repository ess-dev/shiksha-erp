<?php

namespace App\Domain\School\Exceptions;

use DomainException;

/**
 * A change to houses or school settings that the rules do not allow.
 */
class HouseException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to change this.', 403);
    }

    public static function duplicateName(string $name): self
    {
        return new self("A house named [{$name}] already exists.");
    }

    public static function invalidOrder(): self
    {
        return new self('The new order must list every house exactly once.');
    }

    public static function invalidPrefix(): self
    {
        return new self('A prefix uses capital letters, digits and hyphens only, up to 12 characters.');
    }

    public static function invalidDocumentRules(): self
    {
        return new self('Choose which classes each required document applies to.');
    }
}
