<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Enums\ConcessionKind;
use App\Domain\Fees\Enums\ConcessionType;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\InvoiceBiller;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\Action;
use DateTimeImmutable;
use Illuminate\Support\Str;

class SetUpFeeSchedule extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
        private readonly InvoiceBiller $biller,
    ) {}

    /**
     * Creates the current academic year's installments (config
     * `shiksha.fee_schedule`: the terms split the year's months evenly, due on
     * a fixed day of each term's first month), adds the sibling concession if
     * the school has none, and bills every enrolled student whose class
     * already has a fee group. Needs `fee_structures.manage` over the whole
     * school. illustrative — verify the schedule with the school.
     *
     * @return array{installments: int, invoices: int}
     */
    public function handle(Identity $actor): array
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        $year = $this->years->find() ?? throw FeeException::noAcademicYear();

        if (FeeInstallment::query()->where('academic_year_id', $year['key'])->exists()) {
            throw FeeException::scheduleExists();
        }

        $terms = max(1, min(12, (int) config('shiksha.fee_schedule.terms')));
        $dueDay = max(1, min(28, (int) config('shiksha.fee_schedule.due_day')));

        return $this->transaction(function () use ($year, $terms, $dueDay) {
            $start = new DateTimeImmutable($year['starts_on']);
            $end = new DateTimeImmutable($year['ends_on']);
            $months = ((int) $end->format('Y') - (int) $start->format('Y')) * 12 + ((int) $end->format('n') - (int) $start->format('n')) + 1;
            $perTerm = max(1, intdiv($months, $terms));
            $first = $start->modify('first day of this month');

            for ($i = 0; $i < $terms; $i++) {
                $termStart = $first->modify('+'.($i * $perTerm).' months');
                $termEnd = $i === $terms - 1 ? $end : $termStart->modify('+'.($perTerm - 1).' months')->modify('last day of this month');

                FeeInstallment::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'academic_year_id' => $year['key'],
                    'position' => $i + 1,
                    'label' => sprintf('Term %d (%s–%s)', $i + 1, $termStart->format('M'), $termEnd->format('M')),
                    'due_on' => $termStart->setDate((int) $termStart->format('Y'), (int) $termStart->format('n'), $dueDay)->format('Y-m-d'),
                    'ends_on' => $termEnd->format('Y-m-d'),
                ]);
            }

            if (! FeeConcession::query()->where('kind', ConcessionKind::Sibling)->exists()) {
                // illustrative — verify: the prototype gives the second child onward 10% off tuition.
                FeeConcession::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'name' => 'Sibling concession',
                    'kind' => ConcessionKind::Sibling,
                    'value_type' => ConcessionType::Percent,
                    'value' => 10,
                    'applies_to_head' => 'Tuition fee',
                    'needs_approval' => false,
                ]);
            }

            $billed = $this->biller->bill($year['key']);

            $this->audit(new AuditEntry('fees.schedule_created', 'academic_year', $year['ulid'], null, [
                'installments' => $terms,
                'invoices' => $billed['invoices'],
            ]));

            return ['installments' => $terms, 'invoices' => $billed['invoices']];
        });
    }
}
