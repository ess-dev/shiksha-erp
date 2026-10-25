<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Enums\ConcessionKind;
use App\Domain\Fees\Enums\ConcessionType;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class AddConcession extends Action
{
    /** illustrative — verify: the prototype asks for the principal's approval above 50%. */
    public const APPROVAL_ABOVE_PERCENT = 50;

    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
    ) {}

    /**
     * Defines a concession (a percentage of, or a fixed amount off, one fee
     * head). Assigning it to students comes later; a percentage above 50 is
     * flagged as needing the principal's approval when it is assigned. Needs
     * `fee_structures.manage` over the whole school.
     *
     * @param  int  $value  a whole percent, or whole rupees for a fixed amount
     */
    public function handle(Identity $actor, string $name, ConcessionType $type, int $value, string $headName): FeeConcession
    {
        $this->visibility->assertSchoolWide($actor, 'fee_structures.manage');

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 60) {
            throw FeeException::invalidConcession('Enter a name of up to 60 characters.');
        }

        if ($value < 1) {
            throw FeeException::invalidConcession('Enter a value.');
        }

        if ($type === ConcessionType::Percent && $value > 100) {
            throw FeeException::invalidConcession('A percentage can’t be more than 100.');
        }

        $year = $this->years->find() ?? throw FeeException::noAcademicYear();
        $heads = $this->headNames($year['key']);
        $head = $heads[mb_strtolower(trim($headName))] ?? throw FeeException::invalidConcession('Choose one of the fee heads.');

        try {
            return $this->transaction(function () use ($name, $type, $value, $head) {
                $concession = FeeConcession::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'name' => $name,
                    'kind' => ConcessionKind::Standard,
                    'value_type' => $type,
                    'value' => $type === ConcessionType::Percent ? $value : Money::fromRupees($value)->paise,
                    'applies_to_head' => $head,
                    'needs_approval' => $type === ConcessionType::Percent && $value > self::APPROVAL_ABOVE_PERCENT,
                ]);

                $this->audit(new AuditEntry('fees.concession_added', 'fee_concession', $concession->ulid, null, [
                    'name' => $name,
                    'type' => $type->value,
                    'value' => $concession->value,
                    'head' => $head,
                ]));

                return $concession;
            });
        } catch (UniqueConstraintViolationException) {
            throw FeeException::invalidConcession('A concession with this name already exists.');
        }
    }

    /**
     * Every fee head name used by a group of the year, lowercase => as written.
     *
     * @return array<string, string>
     */
    private function headNames(int $yearId): array
    {
        $names = [];

        foreach (FeeGroup::query()->where('academic_year_id', $yearId)->get() as $group) {
            foreach ($group->heads as $head) {
                $names[mb_strtolower($head['name'])] ??= $head['name'];
            }
        }

        return $names;
    }
}
