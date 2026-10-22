<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;

class SetCurrentAcademicYear extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    /**
     * Makes this the school's current academic year; exactly one is current.
     */
    public function handle(Identity $actor, AcademicYear $year): AcademicYear
    {
        $this->guard->assertMayManageSchool($actor);

        return $this->transaction(function () use ($year) {
            $previous = AcademicYear::query()->where('is_current', true)->first();

            if ($previous?->is($year)) {
                $this->audit(new AuditEntry('structure.academic_year_made_current', 'academic_year', $year->ulid, [
                    'previous' => $previous->ulid,
                ], ['unchanged' => true]));

                return $year;
            }

            AcademicYear::query()->where('is_current', true)->update(['is_current' => false]);
            $year->forceFill(['is_current' => true])->save();

            $this->audit(new AuditEntry('structure.academic_year_made_current', 'academic_year', $year->ulid, [
                'previous' => $previous?->ulid,
            ], ['name' => $year->name]));

            return $year;
        });
    }
}
