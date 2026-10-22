<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TransferCertificateData extends Data
{
    /**
     * @param  list<string>  $guardian_names
     */
    public function __construct(
        public string $school_name,
        public string $tc_number,
        public string $student_name,
        public string $admission_number,
        public string $born_on,
        public string $gender,
        public ?string $class_label,
        public string $admitted_on,
        public string $last_day,
        public string $reason,
        public array $guardian_names,
    ) {}
}
