<?php

namespace App\Http\Middleware;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\AbilitiesData;
use App\Domain\Identity\Data\AuthenticatedIdentityData;
use App\Domain\Identity\Models\Identity;
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
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
