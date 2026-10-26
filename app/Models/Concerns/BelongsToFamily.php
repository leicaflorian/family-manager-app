<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Family;
use App\Models\Scopes\FamilyScope;
use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per ogni modello con i dati di una famiglia: lo scope che legge solo la
 * famiglia corrente, `family_id` compilato da solo e la relazione `family()`.
 *
 * @property int $family_id
 * @property-read Family $family
 */
trait BelongsToFamily
{
    /**
     * Collega lo scope e compila `family_id` quando il modello nasce.
     */
    public static function bootBelongsToFamily(): void
    {
        static::addGlobalScope(new FamilyScope);

        static::creating(static function (Model $model): void {
            if ($model->getAttribute('family_id') === null) {
                $model->setAttribute('family_id', CurrentFamily::id());
            }
        });
    }

    /**
     * La famiglia a cui appartiene il dato.
     *
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
