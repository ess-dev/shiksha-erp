<?php

namespace App\Http\Controllers\Fees;

use App\Domain\Fees\Actions\AddConcession;
use App\Domain\Fees\Actions\AssignClassToFeeGroup;
use App\Domain\Fees\Actions\CreateFeeGroup;
use App\Domain\Fees\Actions\DeleteFeeGroup;
use App\Domain\Fees\Actions\RenameFeeGroup;
use App\Domain\Fees\Actions\SaveFeeGroupHeads;
use App\Domain\Fees\Actions\SetUpFeeSchedule;
use App\Domain\Fees\Enums\ConcessionType;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Queries\FeeStructureQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\AddConcessionRequest;
use App\Http\Requests\Fees\AssignClassRequest;
use App\Http\Requests\Fees\CreateFeeGroupRequest;
use App\Http\Requests\Fees\RenameFeeGroupRequest;
use App\Http\Requests\Fees\SaveFeeHeadsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FeeStructureController extends Controller
{
    /**
     * Fee groups, which classes share them, the installment schedule and
     * concessions. Anyone with `fees.view` can read it.
     */
    public function index(Request $request, FeeStructureQuery $structure): Response
    {
        Gate::authorize('fees.view');

        return Inertia::render('fees/structure', [
            'structure' => $structure->get($this->actor($request)),
        ]);
    }

    public function schedule(Request $request, SetUpFeeSchedule $setUp): RedirectResponse
    {
        Gate::authorize('fee_structures.manage');

        $result = $setUp->handle($this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installment schedule created (:terms terms). :invoices installments billed.', [
            'terms' => $result['installments'],
            'invoices' => $result['invoices'],
        ])]);

        return back();
    }

    public function storeGroup(CreateFeeGroupRequest $request, CreateFeeGroup $create): RedirectResponse
    {
        $result = $create->handle(
            $this->actor($request),
            $request->string('name')->toString(),
            $request->filled('copy_from') ? $request->string('copy_from')->toString() : null,
            array_values(array_map(strval(...), (array) $request->input('classes', []))),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created:repriced. Set its fees below.', [
            'name' => $result['group']->name,
            'repriced' => $result['repriced'] > 0 ? ' · '.$result['repriced'].' installments re-priced' : '',
        ])]);

        return back();
    }

    public function updateHeads(SaveFeeHeadsRequest $request, FeeGroup $group, SaveFeeGroupHeads $save): RedirectResponse
    {
        $result = $save->handle($this->actor($request), $group, $request->heads());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved · :count installments re-priced.', ['name' => $group->name, 'count' => $result['repriced']])]);

        return back();
    }

    public function rename(RenameFeeGroupRequest $request, FeeGroup $group, RenameFeeGroup $rename): RedirectResponse
    {
        $rename->handle($this->actor($request), $group, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Renamed.')]);

        return back();
    }

    public function destroyGroup(Request $request, FeeGroup $group, DeleteFeeGroup $delete): RedirectResponse
    {
        Gate::authorize('fee_structures.manage');

        $delete->handle($this->actor($request), $group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return back();
    }

    public function assignClass(AssignClassRequest $request, string $schoolClass, AssignClassToFeeGroup $assign): RedirectResponse
    {
        $group = FeeGroup::query()->where('ulid', $request->string('group')->toString())->firstOrFail();
        $result = $assign->handle($this->actor($request), $schoolClass, $group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Now uses :group · :count installments re-priced.', ['group' => $group->name, 'count' => $result['repriced']])]);

        return back();
    }

    public function storeConcession(AddConcessionRequest $request, AddConcession $add): RedirectResponse
    {
        $add->handle(
            $this->actor($request),
            $request->string('name')->toString(),
            ConcessionType::from($request->string('type')->toString()),
            $request->integer('value'),
            $request->string('head')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Concession added.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
