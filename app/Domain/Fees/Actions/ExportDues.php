<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\DueFilters;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Queries\DuesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class ExportDues extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly DuesQuery $dues,
    ) {}

    /**
     * The unpaid installments the Dues filters match, as CSV rows (header
     * first), and a record that they were exported. Needs `fees.view`; only
     * students within its scope are included, and guardian phone numbers only
     * for students the person may see sensitive details of.
     *
     * @return list<list<string>>
     */
    public function handle(Identity $actor, DueFilters $filters): array
    {
        $this->visibility->assertHolds($actor, 'fees.view');

        return $this->transaction(function () use ($actor, $filters) {
            $rows = $this->dues->exportRows($actor, $filters);

            $this->audit(new AuditEntry('fees.dues_exported', 'fee_dues', null, null, [
                'rows' => count($rows) - 1,
                'status' => $filters->status,
                'section' => $filters->sectionUlid,
                'installment' => $filters->installmentUlid,
                'campus' => $filters->campusUlid,
                'has_search' => trim($filters->search) !== '',
            ]));

            return $rows;
        });
    }
}
