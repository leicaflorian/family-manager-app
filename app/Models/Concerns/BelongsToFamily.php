<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Family;
use App\Models\Scopes\FamilyScope;
use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every model holding a family's data: the scope that reads only the
 * current family, `family_id` filled in automatically and the `family()` relation.
 *
 * @property int $family_id
 * @property-read Family $family
 */
trait BelongsToFamily
{
    /**
     * Adds the scope and fills in `family_id` when the model is created.
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
     * The family the row belongs to.
     *
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
