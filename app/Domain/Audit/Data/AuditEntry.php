<?php

namespace App\Domain\Audit\Data;

/**
 * What happened, to what, with a before/after summary. Summaries hold only the
 * fields that changed and never secrets, OTPs, tokens or document contents.
 */
final readonly class AuditEntry
{
    /**
     * @param  string  $action  Past-tense key, e.g. "fees.payment_recorded"
     * @param  string  $subjectType  e.g. "fee_invoice"
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public string $action,
        public string $subjectType,
        public ?string $subjectUlid = null,
        public ?array $before = null,
        public ?array $after = null,
    ) {}
}
