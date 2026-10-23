<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateHouse;
use App\Domain\School\Actions\ReorderHouses;
use App\Domain\School\Actions\UpdateHouse;
use App\Domain\School\Models\House;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ReorderHousesRequest;
use App\Http\Requests\Students\StoreHouseRequest;
use App\Http\Requests\Students\UpdateHouseRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class HouseController extends Controller
{
    public function store(StoreHouseRequest $request, CreateHouse $createHouse): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $createHouse->handle($identity, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('House added.')]);

        return back();
    }

    public function update(UpdateHouseRequest $request, House $house, UpdateHouse $updateHouse): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updateHouse->handle($identity, $house, $request->string('name')->toString(), $request->string('status')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('House updated.')]);

        return back();
    }

    public function reorder(ReorderHousesRequest $request, ReorderHouses $reorderHouses): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        /** @var list<string> $order */
        $order = array_values((array) $request->input('order'));

        $reorderHouses->handle($identity, $order);

        return back();
    }
}
