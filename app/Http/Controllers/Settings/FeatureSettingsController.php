<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Families\ChangeFeatures;
use App\Data\FeatureSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFeaturesRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → What you use*: the family turns the Budget and the mail for the bills on or off. Everyone sees the
 * switches; only an administrator changes them (checked by the policy).
 */
final class FeatureSettingsController extends Controller
{
    /**
     * The page.
     *
     * @param  User  $user  whoever signed in
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        $family = $user->family;

        return Inertia::render('settings/Features', [
            'settings' => new FeatureSettingsData(
                budget: $family->budget_enabled,
                mail: $family->mail_enabled,
                can_change: Gate::allows('manageFeatures', $family),
            ),
        ]);
    }

    /**
     * Saves the switches.
     *
     * @param  UpdateFeaturesRequest  $request  the two switches
     * @param  ChangeFeatures  $changeFeatures  writes them
     * @param  User  $user  whoever signed in
     */
    public function update(UpdateFeaturesRequest $request, ChangeFeatures $changeFeatures, #[CurrentUser] User $user): RedirectResponse
    {
        $changeFeatures->handle($user->family, $request->boolean('budget'), $request->boolean('mail'));

        return to_route('settings.features.edit');
    }
}
