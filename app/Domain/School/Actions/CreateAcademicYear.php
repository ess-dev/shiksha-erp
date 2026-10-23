<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use Illuminate\Support\Str;

class CreateAcademicYear extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    /**
     * @param  string  $startsOn  Calendar date, YYYY-MM-DD
     * @param  string  $endsOn  Calendar date, YYYY-MM-DD (last day, inclusive)
     */
    public function handle(Identity $actor, string $name, string $startsOn, string $endsOn): AcademicYear
    {
        $this->guard->assertMayManageSchool($actor);

        $startsOn = CalendarDate::parse($startsOn);
        $endsOn = CalendarDate::parse($endsOn);

        if ($endsOn <= $startsOn) {
            throw StructureException::yearEndsBeforeItStarts();
        }

        return $this->transaction(function () use ($name, $startsOn, $endsOn) {
            $overlapping = AcademicYear::query()
                ->where('starts_on', '<=', $endsOn)
                ->where('ends_on', '>=', $startsOn)
                ->first();

            if ($overlapping !== null) {
                throw StructureException::yearOverlaps($overlapping->name);
            }

            $year = AcademicYear::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => trim($name),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'is_current' => false,
            ]);

            $this->audit(new AuditEntry('structure.academic_year_created', 'academic_year', $year->ulid, null, [
                'name' => $year->name, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
            ]));

            return $year;
        });
    }
}
