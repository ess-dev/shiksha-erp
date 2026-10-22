<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\ApplyRoleTemplateUpdate;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\UpdateRolePermissions;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Queries\PermissionOptionsQuery;
use App\Domain\Access\Queries\RolesQuery;
use App\Domain\Access\Queries\RoleUpdatePromptQuery;
use App\Domain\Access\Queries\StaffAccessQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\ScopeOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreRoleRequest;
use App\Http\Requests\Access\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class RoleController extends Controller
{
    /**
     * Roles, who holds them, and the "template update available" prompt.
     */
    public function index(
        Request $request,
        RolesQuery $roles,
        PermissionOptionsQuery $permissions,
        StaffAccessQuery $staff,
        RoleUpdatePromptQuery $updates,
        ScopeOptionsQuery $scopeOptions,
    ): Response {
        Gate::authorize('roles.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return Inertia::render('school/roles', [
            'roles' => $roles->get(),
            'permissions' => $permissions->get($identity),
            'staff' => $staff->get(),
            'updates' => $updates->get(),
            'scopeOptions' => Gate::allows('roles.assign') || Gate::allows('roles.manage') ? $scopeOptions->get() : null,
            'templates' => Gate::allows('roles.manage') ? $roles->templates() : [],
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRole $createRole): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $template = $request->input('template');

        try {
            $createRole->handle(
                $identity,
                $request->string('name')->toString(),
                array_values((array) $request->input('permissions', [])),
                is_string($template) && $template !== '' ? $template : null,
                $request->boolean('confirm_critical'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return back();
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role, UpdateRolePermissions $updatePermissions): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updatePermissions->handle(
            $identity,
            $role,
            array_values((array) $request->input('permissions', [])),
            $request->boolean('confirm_critical'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated.')]);

        return back();
    }

    public function applyUpdate(Request $request, Role $role, ApplyRoleTemplateUpdate $applyUpdate): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        try {
            $applyUpdate->handle($identity, $role, $request->boolean('confirm_critical'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated to the latest template.')]);

        return back();
    }
}
