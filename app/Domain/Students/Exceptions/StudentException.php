<?php

namespace App\Domain\Students\Exceptions;

use DomainException;

/**
 * A change to students, guardians or their documents that the rules do not
 * allow. Code 403 is a refusal, 404 a record outside the person's reach; any
 * other is a rule shown on the form it came from.
 */
class StudentException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do this.', 403);
    }

    public static function notFound(): self
    {
        return new self('Student not found.', 404);
    }

    public static function invalidPhone(string $who): self
    {
        return new self("The phone number for {$who} is not a valid Indian mobile number.");
    }

    public static function guardianPhoneConflict(string $existingName): self
    {
        return new self("This phone number already belongs to the guardian {$existingName}. Use the same guardian details to link the child to that family, or enter a different phone number.");
    }

    public static function guardiansShareAPhone(): self
    {
        return new self('Each guardian needs their own phone number, so two guardians of one child cannot share one.');
    }

    public static function noGuardian(): self
    {
        return new self('A student needs at least one guardian.');
    }

    public static function noCurrentYear(): self
    {
        return new self('Set a current academic year before enrolling students.');
    }

    public static function sectionNotFound(): self
    {
        return new self('That section does not exist in the current academic year.');
    }

    public static function sectionOutOfScope(): self
    {
        return new self('That section is outside your access.');
    }

    public static function sectionFull(string $label): self
    {
        return new self("{$label} is full.");
    }

    public static function wrongClass(): self
    {
        return new self('A student can only move to another section of the same class.');
    }

    public static function alreadyInSection(): self
    {
        return new self('The student is already in that section.');
    }

    public static function notCurrentStudent(): self
    {
        return new self('Only a current student can be changed this way.');
    }

    public static function rollTaken(int $roll): self
    {
        return new self("Roll number {$roll} is already used in this section.");
    }

    public static function admissionNumberTaken(string $number): self
    {
        return new self("Admission number {$number} is already used.");
    }

    public static function siblingWithSelf(): self
    {
        return new self('A student cannot be their own sibling.');
    }

    public static function alreadySiblings(): self
    {
        return new self('These students are already siblings.');
    }

    public static function siblingNeedsPortalAccess(): self
    {
        return new self('Siblings can be linked only when every guardian of both students has portal access.');
    }

    public static function notLinked(): self
    {
        return new self('These students are not linked by staff. Siblings who share a guardian stay linked through that guardian.');
    }

    public static function guardianOutOfReach(string $name): self
    {
        return new self("{$name} is also the guardian of a child outside your access, so only someone with wider access can change their details.");
    }

    public static function documentAlreadyVerified(): self
    {
        return new self('This document is already verified.');
    }

    public static function documentMissing(): self
    {
        return new self('The file for this document is no longer available.');
    }
}
