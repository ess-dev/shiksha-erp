<?php

namespace App\Domain\Students\Data;

/**
 * Where a student currently sits (or last sat): their class label, roll number
 * and campus. Server-side only.
 */
final readonly class StudentPlace
{
    public function __construct(
        public string $classLabel,
        public ?int $rollNumber,
        public string $campusName,
    ) {}
}
