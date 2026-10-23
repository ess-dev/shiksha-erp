<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

/**
 * The authenticator code or recovery code was wrong. The field says which was tried.
 */
class InvalidTwoStepCode extends RuntimeException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct(
            $field === 'recovery_code'
                ? __('The provided recovery code was invalid.')
                : __('The provided two-step code was invalid.'),
        );
    }
}
