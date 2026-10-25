<?php

namespace App\Domain\Fees\Exceptions;

use DomainException;

/**
 * A change the fee rules do not allow. Code 403 is a refusal, 404 a record
 * outside the person's reach (shown as not found), anything else a message
 * shown on the form.
 */
class FeeException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do that.', 403);
    }

    public static function notFound(): self
    {
        return new self('That record was not found.', 404);
    }

    public static function noAcademicYear(): self
    {
        return new self('The school has no current academic year yet.');
    }

    public static function noSchedule(): self
    {
        return new self('Create the installment schedule first.');
    }

    public static function scheduleExists(): self
    {
        return new self('This academic year already has an installment schedule.');
    }

    public static function nameTaken(): self
    {
        return new self('Another fee group already has this name.');
    }

    public static function groupInUse(): self
    {
        return new self('This fee group still has classes. Move them to another group first.');
    }

    public static function alreadyOwnFees(string $className, string $groupName): self
    {
        return new self("{$className} already has its own group ({$groupName}).");
    }

    public static function invalidHeads(string $message): self
    {
        return new self($message);
    }

    public static function invalidConcession(string $message): self
    {
        return new self($message);
    }

    public static function nothingSelected(): self
    {
        return new self('Select at least one installment.');
    }

    public static function amountInvalid(string $message): self
    {
        return new self($message);
    }

    public static function alreadyPaid(): self
    {
        return new self('That installment has nothing left to pay.');
    }

    public static function notPending(): self
    {
        return new self('This payment is no longer waiting for confirmation.');
    }

    public static function noGateway(): self
    {
        return new self('No payment gateway is set up for this school. Ask the platform administrator to choose one.');
    }

    public static function notOnline(): self
    {
        return new self('This payment was taken at the counter, so there is no gateway to ask.');
    }

    public static function notCheque(): self
    {
        return new self('Only cheques are cleared or bounced by hand.');
    }

    public static function notFailed(): self
    {
        return new self('Only a failed payment can be re-checked or sent a pay link.');
    }

    public static function alreadySettled(): self
    {
        return new self('The family has already paid what this payment was for.');
    }

    public static function detailRequired(string $what): self
    {
        return new self("Enter {$what}.");
    }
}
