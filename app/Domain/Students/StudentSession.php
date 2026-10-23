<?php

namespace App\Domain\Students;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SessionSwitcherData;
use App\Domain\School\Queries\AcademicSessionsQuery;

/**
 * Which academic session ("year") a person is looking at in the Students
 * screens. Everyone sees the current session; only holders of
 * `students.view_past_sessions` may choose another. A choice that is not
 * allowed (or no longer exists) falls back to the current session.
 */
class StudentSession
{
    public const PERMISSION = 'students.view_past_sessions';

    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly AcademicSessionsQuery $years,
    ) {}

    public function canChoose(Identity $identity): bool
    {
        return ! $this->scopes->get($identity, self::PERMISSION)->isEmpty();
    }

    /**
     * The session to show: key, ulid, name and whether it is the current one.
     * Null when the school has no current academic year and nothing valid is chosen.
     *
     * @return array{key: int, ulid: string, name: string, is_current: bool}|null
     */
    public function resolve(Identity $identity, ?string $requestedUlid): ?array
    {
        if ($requestedUlid !== null && $requestedUlid !== '' && $this->canChoose($identity)) {
            $chosen = $this->years->find($requestedUlid);

            if ($chosen !== null) {
                return $chosen;
            }
        }

        return $this->years->find();
    }

    /**
     * The top-bar switcher; null when the person cannot choose or there is only one session.
     */
    public function switcher(Identity $identity, ?string $requestedUlid): ?SessionSwitcherData
    {
        if (! $this->canChoose($identity)) {
            return null;
        }

        $sessions = $this->years->all();
        $selected = $this->resolve($identity, $requestedUlid);

        if (count($sessions) < 2 || $selected === null) {
            return null;
        }

        return new SessionSwitcherData($sessions, $selected['ulid']);
    }
}
