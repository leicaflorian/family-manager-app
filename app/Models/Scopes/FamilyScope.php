<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limita ogni query di un modello della famiglia alla famiglia corrente.
 *
 * @implements Scope<Model>
 */
final class FamilyScope implements Scope
{
    /**
     * Aggiunge il filtro sulla famiglia.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $famiglia = CurrentFamily::id();

        // Senza una famiglia non si legge niente: una condizione sempre falsa,
        // mai nessun filtro, che darebbe i dati di tutte le famiglie.
        if ($famiglia === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('family_id'), $famiglia);
    }
}
