<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Enums\ConcessionKind;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Re-prices installments that have not been billed in earnest yet: due after
 * today and with nothing paid. Issued, paid and part-paid installments keep
 * their amounts. Runs inside an Action's transaction.
 *
 * An installment that carries a concession is treated as having the sibling
 * concession (the only concession that is applied automatically today).
 */
class InvoiceRepricer
{
    /**
     * The group's fee heads changed: re-price its future unpaid installments.
     *
     * @return array{repriced: int, kept: int} kept = future installments that already have a payment
     */
    public function afterHeadsChanged(FeeGroup $group, string $today): array
    {
        $sibling = FeeConcession::query()->where('kind', ConcessionKind::Sibling)->first();
        $repriced = 0;

        $future = FeeInvoice::query()->where('fee_group_id', $group->id)->where('due_on', '>', $today);
        $kept = (clone $future)->where('paid_paise', '>', 0)->count();

        (clone $future)->where('paid_paise', 0)->orderBy('id')->each(function (FeeInvoice $invoice) use ($group, $sibling, &$repriced) {
            $this->apply($invoice, $group, $sibling);
            $repriced++;
        });

        return ['repriced' => $repriced, 'kept' => $kept];
    }

    /**
     * A class moved to another fee group: re-price its students' future
     * unpaid installments to the new group.
     *
     * @return int installments re-priced
     */
    public function afterClassMoved(int $yearId, int $classId, FeeGroup $target, string $today): int
    {
        $sibling = FeeConcession::query()->where('kind', ConcessionKind::Sibling)->first();
        $studentIds = $this->studentIdsOfClass($yearId, $classId);

        if ($studentIds === []) {
            return 0;
        }

        $repriced = 0;

        FeeInvoice::query()
            ->where('academic_year_id', $yearId)->whereIn('student_id', $studentIds)
            ->where('due_on', '>', $today)->where('paid_paise', 0)
            ->orderBy('id')
            ->each(function (FeeInvoice $invoice) use ($target, $sibling, &$repriced) {
                $this->apply($invoice, $target, $sibling);
                $repriced++;
            });

        return $repriced;
    }

    private function apply(FeeInvoice $invoice, FeeGroup $group, ?FeeConcession $sibling): void
    {
        $price = InvoicePricing::price($group->heads, $invoice->concession_paise > 0 ? $sibling : null);

        $invoice->forceFill([
            'fee_group_id' => $group->id,
            'heads' => $group->heads,
            'gross_paise' => $price['gross_paise'],
            'concession_paise' => $price['concession_paise'],
            'concession_note' => $price['note'],
            'net_paise' => $price['net_paise'],
        ])->save();
    }

    /**
     * @return list<int>
     */
    private function studentIdsOfClass(int $yearId, int $classId): array
    {
        return array_values(array_map(intval(...), DB::connection(config('multitenancy.tenant_database_connection_name'))->table('enrollments as e')
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->where('e.academic_year_id', $yearId)->where('e.is_current', true)->where('s.school_class_id', $classId)
            ->pluck('e.student_id')->all()));
    }
}
