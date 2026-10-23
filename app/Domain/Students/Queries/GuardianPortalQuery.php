<?php

namespace App\Domain\Students\Queries;

use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\Models\Guardian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who may use the parent portal of this school: a guardian with portal access
 * to at least one child who is still enrolled. Guardians without portal access
 * are never linked to an identity.
 */
class GuardianPortalQuery
{
    public function phoneHasPortalAccess(string $phoneE164): bool
    {
        return $this->withPortalAccess()->where('guardians.phone', $phoneE164)->exists();
    }

    /**
     * Email addresses on file for the guardian with this phone (development mail driver only).
     *
     * @return list<string>
     */
    public function emailsForPhone(string $phoneE164): array
    {
        return array_values(Guardian::query()
            ->where('phone', $phoneE164)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->all());
    }

    /**
     * Whether the signed-in identity is still a guardian with portal access
     * here. Checked on every parent request, so removing access ends the session.
     */
    public function identityHasPortalAccess(string $identityUlid): bool
    {
        return $this->withPortalAccess()->where('guardians.identity_ulid', $identityUlid)->exists();
    }

    /**
     * @return Builder<Guardian>
     */
    private function withPortalAccess(): Builder
    {
        return Guardian::query()->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('guardian_student')
                ->join('students', 'students.id', '=', 'guardian_student.student_id')
                ->whereColumn('guardian_student.guardian_id', 'guardians.id')
                ->where('guardian_student.has_portal_access', true)
                ->where('students.status', StudentStatus::Enrolled->value);
        });
    }
}
