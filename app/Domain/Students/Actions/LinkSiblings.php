<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentSiblingLink;
use App\Domain\Students\SiblingFinder;
use App\Domain\Students\StudentGuard;
use Illuminate\Support\Facades\DB;

class LinkSiblings extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly SiblingFinder $siblings,
    ) {}

    /**
     * Links two students as siblings when their guardians differ (students who
     * share a guardian are siblings already). Allowed only when every guardian
     * of both students has portal access. The link is staff-only: it shows the
     * sibling label and links on staff screens and gives parents nothing extra.
     * Needs `students.link_siblings` over both students.
     */
    public function handle(Identity $actor, Student $student, Student $sibling): void
    {
        if ($student->is($sibling)) {
            throw StudentException::siblingWithSelf();
        }

        $this->guard->assertMayOn($actor, 'students.link_siblings', $student);
        $this->guard->assertMayOn($actor, 'students.link_siblings', $sibling);

        $this->transaction(function () use ($student, $sibling) {
            if ($this->siblings->sharesGuardian($student->id, $sibling->id) || $this->siblings->isLinkedByStaff($student->id, $sibling->id)) {
                throw StudentException::alreadySiblings();
            }

            foreach ([$student, $sibling] as $one) {
                $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))
                    ->table('guardian_student')->where('student_id', $one->id)->pluck('has_portal_access');

                if ($rows->isEmpty() || $rows->contains(false)) {
                    throw StudentException::siblingNeedsPortalAccess();
                }
            }

            [$low, $high] = $this->siblings->ordered($student->id, $sibling->id);

            StudentSiblingLink::query()->create(['student_id' => $low, 'sibling_id' => $high]);

            $this->audit(new AuditEntry('students.siblings_linked', 'student', $student->ulid, null, ['sibling' => $sibling->ulid]));
        });
    }
}
