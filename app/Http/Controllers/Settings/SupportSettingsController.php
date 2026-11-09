<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → Support*: the family lets support look at the app as one of its accounts for 24 hours. Everyone sees
 * whether the permission is on; only an administrator gives or takes it back (checked by the policy).
 */
final class SupportSettingsController extends Controller
{
    /**
     * The page.
     *
     * @param  User  $user  whoever signed in
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        $until = $user->family->support_until;

        return Inertia::render('settings/Support', [
            'support_until' => $until !== null && $until->isFuture() ? $until->toIso8601String() : null,
            'admin_level' => Role::Admin->label(),
            'can_change' => Gate::allows('manageSupport', $user->family),
        ]);
    }

    /**
     * Lets support in for 24 hours from now. Giving it again starts the 24 hours over.
     *
     * @param  User  $user  whoever signed in
     */
    public function store(#[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('manageSupport', $user->family);

        $user->family->forceFill(['support_until' => now()->addHours(24)])->save();

        return to_route('settings.support.edit');
    }

    /**
     * Takes the permission back.
     *
     * @param  User  $user  whoever signed in
     */
    public function destroy(#[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('manageSupport', $user->family);

        $user->family->forceFill(['support_until' => null])->save();

        return to_route('settings.support.edit');
    }
}
