<?php

namespace App\Http\Middleware;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\AbilitiesData;
use App\Domain\Access\TwoStepRequirement;
use App\Domain\Identity\Data\AuthenticatedIdentityData;
use App\Domain\Identity\Models\Identity;
use App\Domain\Platform\Data\PlatformUserData;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\School\Data\CampusOptionData;
use App\Domain\School\Data\CampusSwitcherData;
use App\Domain\School\Data\SessionSwitcherData;
use App\Domain\School\Queries\AccessibleCampusesQuery;
use App\Domain\Students\StudentSession;
use App\Tenancy\Tenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() instanceof Identity
                    ? AuthenticatedIdentityData::fromIdentity($request->user())
                    : null,
                'abilities' => $request->user() instanceof Identity
                    ? new AbilitiesData(
                        app(Authorizer::class)->heldPermissions($request->user()),
                        app(Authorizer::class)->holdsOwnerRole($request->user()),
                    )
                    : null,
                'two_step_required' => $request->user() instanceof Identity
                    && app(TwoStepRequirement::class)->isRequiredFor($request->user())
                    && ! app(TwoStepRequirement::class)->isSatisfiedBy($request->user()),
            ],
            'campusSwitcher' => fn () => $this->campusSwitcher($request),
            'sessionSwitcher' => fn () => $this->sessionSwitcher($request),
            'platform' => [
                'user' => $request->user('platform') instanceof PlatformUser
                    ? PlatformUserData::fromUser($request->user('platform'))
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The top-bar session switcher: only for people holding
     * `students.view_past_sessions` in a school with more than one session.
     */
    private function sessionSwitcher(Request $request): ?SessionSwitcherData
    {
        $user = $request->user();

        if (! $user instanceof Identity || ! Tenant::current() instanceof Tenant) {
            return null;
        }

        $chosen = $request->session()->get('school.session');

        return app(StudentSession::class)->switcher($user, is_string($chosen) ? $chosen : null);
    }

    /**
     * The top-bar campus switcher: only for people who can reach more than one
     * campus. A remembered choice that is no longer reachable is ignored.
     */
    private function campusSwitcher(Request $request): ?CampusSwitcherData
    {
        $user = $request->user();

        if (! $user instanceof Identity || ! Tenant::current() instanceof Tenant) {
            return null;
        }

        $campuses = app(AccessibleCampusesQuery::class)->get($user);

        if (count($campuses) < 2) {
            return null;
        }

        $chosen = $request->session()->get('school.campus');
        $reachable = array_map(fn (CampusOptionData $campus) => $campus->ulid, $campuses);

        return new CampusSwitcherData($campuses, is_string($chosen) && in_array($chosen, $reachable, true) ? $chosen : null);
    }
}
