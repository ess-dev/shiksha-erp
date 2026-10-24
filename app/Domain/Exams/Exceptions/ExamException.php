<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

/**
 * A change the exam rules do not allow. Code 403 is a refusal, 404 an exam
 * outside the person's reach (shown as not found), anything else a message
 * shown on the form.
 */
class ExamException extends DomainException
{
    public static function notAllowed(): self
    {
        return new self('You are not allowed to do that with this exam.', 403);
    }

    public static function notFound(): self
    {
        return new self('That exam was not found.', 404);
    }

    public static function duplicateName(): self
    {
        return new self('Another exam has this name.');
    }

    public static function pastStart(): self
    {
        return new self('New exams can’t start in the past.');
    }

    public static function closedDay(string $date, string $reason): self
    {
        return new self("{$date} is closed: {$reason}.");
    }

    public static function noSection(): self
    {
        return new self('No section matches these classes and campuses.');
    }

    public static function outsideReach(): self
    {
        return new self('Some of these classes or campuses are outside your reach.');
    }

    public static function notEditable(): self
    {
        return new self('Only scheduled exams and exams with marking open can be changed.');
    }

    public static function badTransition(string $from, string $to): self
    {
        return new self("An exam that is {$from} can’t go to {$to}.");
    }

    public static function cannotDelete(): self
    {
        return new self('This exam has marks or results, so it can’t be deleted. Cancel it instead.');
    }

    public static function badDateSheet(string $message): self
    {
        return new self($message);
    }

    public static function noAcademicYear(): self
    {
        return new self('Set a current academic year first.');
    }
}
