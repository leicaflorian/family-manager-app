<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SkipChecklistStepRequest;
use App\Models\FamilySetting;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * The two buttons of *To begin* on the Home: *Hide*, which hides the list for the account, and *Not needed*, which
 * takes a step away for the whole family.
 */
final class ChecklistController extends Controller
{
    /**
     * Hides the list for whoever pressed it; the others of the family keep seeing it.
     *
     * @param  User  $user  whoever signed in
     */
    public function hide(#[CurrentUser] User $user): RedirectResponse
    {
        $user->forceFill(['checklist_hidden_at' => now()])->save();

        return to_route('dashboard');
    }

    /**
     * Takes a step away for the whole family.
     *
     * @param  SkipChecklistStepRequest  $request  the step, `people` or `invite`
     */
    public function skip(SkipChecklistStepRequest $request): RedirectResponse
    {
        FamilySetting::current()->skipChecklistStep($request->string('step')->toString());

        return to_route('dashboard');
    }
}
