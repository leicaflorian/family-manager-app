<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\AreaCountData;
use App\Data\AreaSettingData;
use App\Data\AreaSettingsData;
use App\Enums\Area;
use App\Http\Requests\Settings\UpdateAreasRequest;
use App\Models\FamilySetting;
use App\Models\User;
use App\Services\CardSummaries;
use App\Services\FamilyAreas;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → Areas*: the order of the areas and the hidden ones. Everyone sees them; only an administrator saves them.
 */
final class AreaSettingsController extends Controller
{
    /**
     * The page: every area in the order of the family, hidden ones included.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyAreas  $areas  the choice of the family
     * @param  CardSummaries  $summaries  the counts of active cards
     */
    public function edit(#[CurrentUser] User $user, FamilyAreas $areas, CardSummaries $summaries): Response
    {
        return Inertia::render('settings/Areas', ['settings' => new AreaSettingsData(
            areas: array_map(fn (AreaCountData $row): AreaSettingData => new AreaSettingData(
                area: $row->area,
                label: $row->label,
                count: $row->count,
                hidden: $areas->isHidden(Area::from($row->area)),
            ), $summaries->countsFor($areas->ordered())),
            can_change: $user->can('create', FamilySetting::class),
        )]);
    }

    /**
     * Saves the order and the hidden areas.
     *
     * @param  UpdateAreasRequest  $request  the choice, checked
     * @param  FamilyAreas  $areas  the choice of the family
     */
    public function update(UpdateAreasRequest $request, FamilyAreas $areas): RedirectResponse
    {
        $areas->save($request->order(), $request->hidden());

        return to_route('settings.areas.edit');
    }

    /**
     * Goes back to the default order with nothing hidden.
     *
     * @param  FamilyAreas  $areas  the choice of the family
     */
    public function reset(FamilyAreas $areas): RedirectResponse
    {
        Gate::authorize('create', FamilySetting::class);

        $areas->reset();

        return to_route('settings.areas.edit');
    }
}
