<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A student the accountant is collecting for: who they are, the guardian to
 * take the money from and what is open. `due_paise` is what falls due within
 * the next 30 days or is overdue.
 */
#[TypeScript]
class CollectStudentData extends Data
{
    /**
     * @param  list<CollectInstallmentData>  $installments  Oldest due date first
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $admission_number,
        public string $section_label,
        public string $campus_name,
        public ?string $guardian_name,
        public ?string $guardian_relation,
        public int $due_paise,
        public array $installments,
    ) {}
}
