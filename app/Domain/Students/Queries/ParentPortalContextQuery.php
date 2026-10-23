<?php

namespace App\Domain\Students\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SessionSwitcherData;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Data\ParentPortalContext;
use App\Domain\Students\Data\ParentShellData;
use App\Domain\Students\Exceptions\StudentException;
use App\Tenancy\Tenant;

/**
 * Works out the guardian, the academic session and the child a parent page is
 * about. A child that is not the guardian's (or not enrolled in the viewed
 * session) is "not found", so one family cannot probe another.
 */
class ParentPortalContextQuery
{
    public function __construct(
        private readonly GuardianChildrenQuery $children,
        private readonly AcademicSessionsQuery $sessions,
    ) {}

    /**
     * @throws StudentException 404 when the requested child is not one of the guardian's
     */
    public function resolve(Identity $identity, ?string $childUlid, ?string $sessionUlid, bool $hasStaffContext): ParentPortalContext
    {
        $current = $this->sessions->find();
        $chosen = $sessionUlid !== null && $sessionUlid !== '' ? $this->sessions->find($sessionUlid) : null;
        $session = $chosen ?? $current;

        if ($session === null) {
            throw StudentException::notFound();
        }

        $children = $this->children->forYear($identity, $session['key'], $session['is_current']);
        $selected = $this->select($children, $childUlid);

        $sessionOptions = $this->sessions->all();
        $guardian = $this->children->guardian($identity);
        $name = $guardian === null ? 'Parent' : $guardian->name;

        $shell = new ParentShellData(
            Tenant::current()->name,
            $name,
            explode(' ', trim($name))[0],
            array_map(fn (GuardianChildRef $child) => $child->toData(), $children),
            $selected?->ulid,
            count($sessionOptions) > 1 ? new SessionSwitcherData($sessionOptions, $session['ulid']) : null,
            $hasStaffContext,
            Tenant::current()?->hasModule('attendance') ?? false,
            (string) config('shiksha.timezone'),
        );

        return new ParentPortalContext($shell, $selected, $session);
    }

    /**
     * @param  list<GuardianChildRef>  $children
     */
    private function select(array $children, ?string $childUlid): ?GuardianChildRef
    {
        if ($childUlid === null || $childUlid === '') {
            return $children[0] ?? null;
        }

        foreach ($children as $child) {
            if ($child->ulid === $childUlid) {
                return $child;
            }
        }

        throw StudentException::notFound();
    }
}
