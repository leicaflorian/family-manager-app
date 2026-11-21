<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Consumption\SaveConsumption;
use App\Enums\ConsumptionSource;
use App\Http\Requests\UtilityConsumptionRequest;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The consumptions written by hand from the panel of the Consumption page: added, corrected and deleted. A row saved
 * here is `manual`, and a reading of its bill never overwrites it. Each answer goes back to the page as it was.
 */
final class UtilityConsumptionController extends Controller
{
    public function __construct(private readonly SaveConsumption $save) {}

    /**
     * Adds a consumption of the utility, or writes the one of a bill whose reading did not find it.
     *
     * @param  UtilityConsumptionRequest  $request  the figures, checked
     * @param  Utility  $utility  the utility of the address, already found in the family
     */
    public function store(UtilityConsumptionRequest $request, Utility $utility): RedirectResponse
    {
        $this->save->handle($utility, $request->values(), ConsumptionSource::Manual, $request->document());

        return back();
    }

    /**
     * Corrects a consumption: it becomes written by hand.
     *
     * @param  UtilityConsumptionRequest  $request  the figures, checked
     * @param  UtilityConsumption  $consumption  the row of the address, already found in the family
     */
    public function update(UtilityConsumptionRequest $request, UtilityConsumption $consumption): RedirectResponse
    {
        $this->save->handle($consumption->utility, $request->values(), ConsumptionSource::Manual, row: $consumption);

        return back();
    }

    /**
     * Deletes a consumption (only an `admin`). Its bill stays.
     *
     * @param  UtilityConsumption  $consumption  the row of the address, already found in the family
     */
    public function destroy(UtilityConsumption $consumption): RedirectResponse
    {
        Gate::authorize('delete', $consumption);

        $consumption->delete();

        return back();
    }
}
