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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class CreateFeeGroup extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
        private readonly FeeClasses $classes,
        private readonly InvoiceRepricer $repricer,
        private readonly InvoiceBiller $biller,
    ) {}

    /**
     * Adds a fee group to the current academic year, optionally starting from
     * another group's fee heads, and moves the chosen classes into it (their
     * future unpaid installments are re-priced and unbilled students billed).
     * Needs `fee_structures.manage` over the whole school.
     *
     * @param  list<string>  $classUlids
     * @return array{group: FeeGroup, repriced: int}
     */
    public function handle(Identity $actor, string $name, ?string $copyFromUlid, array $classUlids): array
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 40) {
            throw FeeException::invalidHeads('Enter a group name of up to 40 characters.');
        }

        $year = $this->years->find() ?? throw FeeException::noAcademicYear();
        $source = $copyFromUlid === null || $copyFromUlid === '' ? null : (FeeGroup::query()->where('academic_year_id', $year['key'])->where('ulid', $copyFromUlid)->first() ?? throw FeeException::notFound());
        $classes = [];

        foreach (array_values(array_unique($classUlids)) as $ulid) {
            $classes[] = $this->classes->find($ulid) ?? throw FeeException::notFound();
        }

        if (count($classes) === 1) {
            $this->assertNotAlreadyAlone($year['key'], $classes[0]);
        }

        try {
            return $this->transaction(function () use ($name, $source, $classes, $year) {
                $group = FeeGroup::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'academic_year_id' => $year['key'],
                    'name' => $name,
                    'heads' => $source === null ? [] : $source->heads,
                ]);

                $repriced = 0;
                $today = CalendarDate::today();

                foreach ($classes as $class) {
                    FeeGroupClass::query()->updateOrCreate(
                        ['academic_year_id' => $year['key'], 'school_class_id' => $class['id']],
                        ['fee_group_id' => $group->id],
                    );

                    $repriced += $this->repricer->afterClassMoved($year['key'], $class['id'], $group, $today);
                    $this->biller->bill($year['key'], null, false, $class['id']);
                }

                $this->audit(new AuditEntry('fees.group_created', 'fee_group', $group->ulid, null, [
                    'name' => $name,
                    'copied_from' => $source?->ulid,
                    'classes' => array_column($classes, 'ulid'),
                    'repriced' => $repriced,
                ]));

                return ['group' => $group, 'repriced' => $repriced];
            });
        } catch (UniqueConstraintViolationException) {
            throw FeeException::nameTaken();
        }
    }

    /**
     * Giving a class "its own fees" makes no sense when it already is the only class of its group.
     *
     * @param  array{id: int, ulid: string, name: string, level: int}  $class
     */
    private function assertNotAlreadyAlone(int $yearKey, array $class): void
    {
        $current = FeeGroupClass::query()->where('academic_year_id', $yearKey)->where('school_class_id', $class['id'])->first();

        if ($current === null) {
            return;
        }

        $others = FeeGroupClass::query()->where('fee_group_id', $current->fee_group_id)->where('school_class_id', '!=', $class['id'])->exists();

        if (! $others) {
            throw FeeException::alreadyOwnFees($class['name'], (string) FeeGroup::query()->whereKey($current->fee_group_id)->value('name'));
        }
    }
}
