<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\InvoiceBiller;
use App\Domain\Fees\InvoiceRepricer;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use InvalidArgumentException;

class SaveFeeGroupHeads extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly InvoiceRepricer $repricer,
        private readonly InvoiceBiller $biller,
    ) {}

    /**
     * Saves a fee group's heads (name and rupees per installment). Only
     * installments due after today with nothing paid are re-priced; billed,
     * paid and part-paid ones keep their amounts. Needs
     * `fee_structures.manage` over the whole school.
     *
     * @param  list<array{name: string, amount: int|string}>  $heads  amounts in whole rupees
     * @return array{repriced: int, kept: int}
     */
    public function handle(Identity $actor, FeeGroup $group, array $heads): array
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        $clean = $this->clean($heads);

        return $this->transaction(function () use ($group, $clean) {
            $before = $group->heads;
            $group->forceFill(['heads' => $clean])->save();

            $result = $this->repricer->afterHeadsChanged($group, CalendarDate::today());

            // Students of its classes who could not be billed while the group had no heads are billed now.
            $billed = 0;

            foreach (FeeGroupClass::query()->where('fee_group_id', $group->id)->pluck('school_class_id') as $classId) {
                $billed += $this->biller->bill($group->academic_year_id, null, false, (int) $classId)['invoices'];
            }

            $this->audit(new AuditEntry('fees.group_heads_saved', 'fee_group', $group->ulid, ['heads' => $before], [
                'heads' => $clean,
                'repriced' => $result['repriced'],
                'kept' => $result['kept'],
                'billed' => $billed,
            ]));

            return $result;
        });
    }

    /**
     * @param  list<array{name: string, amount: int|string}>  $heads
     * @return list<array{name: string, amount_paise: int}>
     */
    private function clean(array $heads): array
    {
        if ($heads === []) {
            throw FeeException::invalidHeads('Add at least one fee head.');
        }

        $clean = [];
        $seen = [];

        foreach ($heads as $head) {
            $name = trim($head['name']);

            if ($name === '' || mb_strlen($name) > 60) {
                throw FeeException::invalidHeads('Every fee head needs a name of up to 60 characters.');
            }

            if (isset($seen[mb_strtolower($name)])) {
                throw FeeException::invalidHeads('Two fee heads have the same name.');
            }

            $seen[mb_strtolower($name)] = true;

            try {
                $amount = Money::fromRupees($head['amount']);
            } catch (InvalidArgumentException) {
                throw FeeException::invalidHeads('Amounts must be whole rupees, zero or more.');
            }

            $clean[] = ['name' => $name, 'amount_paise' => $amount->paise];
        }

        return $clean;
    }
}
