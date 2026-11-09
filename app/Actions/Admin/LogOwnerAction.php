<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;
use App\Models\Owner;
use App\Models\OwnerAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Writes a line in the log of the owner: the owner of the `owner` guard, the family (with its name, which stays
 * when the family goes), and the account for a visit.
 */
final class LogOwnerAction
{
    /**
     * Writes the line.
     *
     * @param  OwnerActionType  $type  what was done
     * @param  Family  $family  the family it was done to
     * @param  User|null  $user  the account involved, for a visit
     * @param  Owner|null  $owner  the owner who did it, when it is not the one of the `owner` guard (a visit that ends
     *                             because the owner signed out of the panel)
     */
    public function handle(OwnerActionType $type, Family $family, ?User $user = null, ?Owner $owner = null): OwnerAction
    {
        $owner ??= Auth::guard('owner')->user();

        return OwnerAction::query()->create([
            'owner_id' => $owner instanceof Owner ? $owner->id : null,
            'family_id' => $family->id,
            'family_name' => $family->name,
            'user_id' => $user?->id,
            'action' => $type,
        ]);
    }
}
