<?php

namespace App\Domain\Students\Data;

/**
 * One CSV row after checking: the student it would create (null when it has
 * errors) with what was wrong or worth a look. Server-side only.
 */
final readonly class ImportRow
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $line,
        public string $studentName,
        public ?string $details,
        public ?string $classLabel,
        public string $guardianLabel,
        public ?NewStudent $student,
        public array $errors,
        public array $warnings,
        public bool $joinsExistingFamily,
    ) {}

    public function isReady(): bool
    {
        return $this->errors === [] && $this->student !== null;
    }
}
