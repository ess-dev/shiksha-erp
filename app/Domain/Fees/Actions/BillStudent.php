<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\InvoiceBiller;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;

class BillStudent extends Action
{
    public function __construct(private readonly InvoiceBiller $biller) {}

    /**
     * Bills a student who has just been enrolled (called by the Students
     * module as part of enrolment, so the caller's permission already
     * covered it). Does nothing when the school has no fees module, no
     * installment schedule yet, or no fee group for the student's class.
     *
     * @return int invoices created
     */
    public function handle(int $studentKey, string $studentUlid, int $yearKey): int
    {
        if (Tenant::current()?->hasModule('fees') !== true) {
            return 0;
        }

        if (! FeeInstallment::query()->where('academic_year_id', $yearKey)->exists() || ! FeeGroupClass::query()->where('academic_year_id', $yearKey)->exists()) {
            return 0;
        }

        return $this->transaction(function () use ($studentKey, $studentUlid, $yearKey) {
            $result = $this->biller->bill($yearKey, [$studentKey]);

            $this->audit(new AuditEntry('fees.student_billed', 'student', $studentUlid, null, ['invoices' => $result['invoices']]));

            return $result['invoices'];
        });
    }
}
