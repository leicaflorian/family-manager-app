<?php

declare(strict_types=1);

namespace App\Admin;

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\Scopes\FamilyScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * The phones linked to a family, for the owner panel. The panel has no current family, so the family scope
 * is lifted here, for the phones of one family only: they show a name and a last use, never any content.
 */
final class FamilyDevices
{
    /**
     * The linked phones of a family, the most recently used first.
     *
     * @param  Family  $family  the family
     * @return Builder<LinkedDevice>
     */
    public static function of(Family $family): Builder
    {
        return LinkedDevice::query()
            ->withoutGlobalScope(FamilyScope::class)
            ->where('family_id', $family->id)
            ->with('user:id,name')
            ->latest('last_used_at');
    }
}
