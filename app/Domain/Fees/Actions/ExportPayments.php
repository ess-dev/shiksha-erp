<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\PaymentFilters;
use App\Domain\Fees\Queries\PaymentsQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class ExportPayments extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly PaymentsQuery $payments,
    ) {}

    /**
     * The payments the Payments filters match, as CSV rows (header first),
     * and a record that they were exported. Needs `fees.view`; only students
     * within its scope are included.
     *
     * @return list<list<string>>
     */
    public function handle(Identity $actor, PaymentFilters $filters): array
    {
        $this->visibility->assertHolds($actor, 'fees.view');

        return $this->transaction(function () use ($actor, $filters) {
            $rows = $this->payments->exportRows($actor, $filters);

            $this->audit(new AuditEntry('fees.payments_exported', 'fee_payments', null, null, [
                'rows' => count($rows) - 1,
                'status' => $filters->status,
                'mode' => $filters->mode,
                'from' => $filters->from,
                'to' => $filters->to,
                'campus' => $filters->campusUlid,
                'has_search' => trim($filters->search) !== '',
            ]));

            return $rows;
        });
    }
}
