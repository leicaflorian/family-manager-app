<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query of a family model to the current family.
 *
 * @implements Scope<Model>
 */
final class FamilyScope implements Scope
{
    /**
     * Adds the filter on the family.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $family = CurrentFamily::id();

        // Without a family nothing is read: an always-false condition, never no
        // filter at all, which would return the data of every family.
        if ($family === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('family_id'), $family);
    }
}
