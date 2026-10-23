<?php

namespace App\Domain\Admissions\Exceptions;

use DomainException;

/**
 * A change to an application that the rules do not allow. Code 403 is a
 * refusal, 404 a record outside the person's reach; any other is a rule
 * shown on the screen it came from.
 */
class AdmissionException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do this.', 403);
    }

    public static function notFound(): self
    {
        return new self('Application not found.', 404);
    }

    public static function invalidPhone(): self
    {
        return new self('Enter a 10-digit Indian mobile number.');
    }

    public static function noCurrentYear(): self
    {
        return new self('Set a current academic year before taking admissions.');
    }

    public static function classNotAtCampus(string $campus, string $class): self
    {
        return new self("{$campus} doesn't have {$class}.");
    }

    public static function closed(): self
    {
        return new self('Enrolled and closed applications can’t be changed.');
    }

    public static function detailsIncomplete(): self
    {
        return new self('Add the date of birth, address and blood group before moving past the Application stage.');
    }

    public static function documentsMissing(int $count): self
    {
        return new self("{$count} required document(s) aren’t ticked.");
    }

    public static function useEnrol(): self
    {
        return new self('Enrol the student from the Decision stage once the application is accepted.');
    }

    public static function notAccepted(): self
    {
        return new self('Record an “Accept” decision before enrolling.');
    }

    public static function sectionNotAvailable(): self
    {
        return new self('Choose a section of the applied class that you can enrol into.');
    }

    public static function sectionFull(string $label): self
    {
        return new self("{$label} is full.");
    }

    public static function decisionStage(): self
    {
        return new self('Decisions are recorded at the Decision stage.');
    }

    public static function invalidDocument(): self
    {
        return new self('Upload a PDF, JPEG or PNG file of at most 5 MB.');
    }

    public static function documentMissing(): self
    {
        return new self('The file for this document is no longer available.');
    }

    public static function invalidBornOn(): self
    {
        return new self('Enter a valid date of birth: not in the future and at least 2 years ago.');
    }
}
