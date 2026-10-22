<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\RemoveRoleAssignment;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreRoleAssignmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

class RoleAssignmentController extends Controller
{
    public function store(StoreRoleAssignmentRequest $request, Role $role, Staff $staff, AssignRole $assignRole): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        try {
            $assignRole->handle($identity, $staff, $role, $this->scope($request), $request->boolean('confirm_critical'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role assigned.')]);

        return back();
    }

    public function destroy(Request $request, RoleAssignment $assignment, RemoveRoleAssignment $removeAssignment): RedirectResponse
    {
        Gate::authorize('roles.assign');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $removeAssignment->handle($identity, $assignment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role removed.')]);

        return back();
    }

    private function scope(StoreRoleAssignmentRequest $request): Scope
    {
        /** @var list<string> $campuses */
        $campuses = array_values((array) $request->input('campuses', []));
        /** @var list<string> $sections */
        $sections = array_values((array) $request->input('sections', []));

        return match (ScopeType::from($request->string('scope_type')->toString())) {
            ScopeType::School => Scope::school(),
            ScopeType::Campus => Scope::campuses($campuses),
            ScopeType::Wing => Scope::wing($campuses, $request->integer('from_level'), $request->integer('to_level')),
            ScopeType::Sections => Scope::sections($sections),
        };
    }
}
