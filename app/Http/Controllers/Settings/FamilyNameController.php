<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Data\FamilyNameSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFamilyNameRequest;
use App\Models\FamilySetting;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → Family name*: the name of the family of whoever signed in. Everyone sees it; only an administrator
 * changes it (the request asks the policy).
 */
final class FamilyNameController extends Controller
{
    /**
     * The page.
     *
     * @param  User  $user  whoever signed in
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Family', ['settings' => new FamilyNameSettingsData(
            name: $user->family->name,
            can_edit: $user->can('update', FamilySetting::query()->firstOrCreate([])),
        )]);
    }

    /**
     * Saves the new name. The address for the bills, made from the name once, stays the same.
     *
     * @param  UpdateFamilyNameRequest  $request  the name, checked
     * @param  User  $user  whoever signed in
     */
    public function update(UpdateFamilyNameRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->family->update(['name' => $request->name()]);

        return to_route('settings.family.edit');
    }
}
