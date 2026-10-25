<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeClasses;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\InvoiceBiller;
use App\Domain\Fees\InvoiceRepricer;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;

class AssignClassToFeeGroup extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
        private readonly FeeClasses $classes,
        private readonly InvoiceRepricer $repricer,
        private readonly InvoiceBiller $biller,
    ) {}

    /**
     * Puts a class in a fee group for the current academic year. Its students'
     * future installments with nothing paid are re-priced; students who were
     * never billed (the class had no group) are billed now. Needs
     * `fee_structures.manage` over the whole school.
     *
     * @return array{repriced: int, billed: int}
     */
    public function handle(Identity $actor, string $classUlid, FeeGroup $group): array
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        $year = $this->years->find() ?? throw FeeException::noAcademicYear();

        if ($group->academic_year_id !== $year['key']) {
            throw FeeException::notFound();
        }

        $class = $this->classes->find($classUlid) ?? throw FeeException::notFound();

        return $this->transaction(function () use ($class, $group, $year) {
            $previous = FeeGroupClass::query()->where('academic_year_id', $year['key'])->where('school_class_id', $class['id'])->first();

            FeeGroupClass::query()->updateOrCreate(
                ['academic_year_id' => $year['key'], 'school_class_id' => $class['id']],
                ['fee_group_id' => $group->id],
            );

            $repriced = $this->repricer->afterClassMoved($year['key'], $class['id'], $group, CalendarDate::today());
            $billed = $this->biller->bill($year['key'], null, false, $class['id']);

            $this->audit(new AuditEntry('fees.class_moved', 'school_class', $class['ulid'], [
                'fee_group' => $previous === null ? null : FeeGroup::query()->whereKey($previous->fee_group_id)->value('ulid'),
            ], [
                'fee_group' => $group->ulid,
                'repriced' => $repriced,
                'billed' => $billed['invoices'],
            ]));

            return ['repriced' => $repriced, 'billed' => $billed['invoices']];
        });
    }
}
