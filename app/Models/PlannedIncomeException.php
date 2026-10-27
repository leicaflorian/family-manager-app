<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEccezione;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedIncomeExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un'eccezione di un'entrata prevista (`planned_income_exceptions`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $planned_income_id
 * @property TipoEccezione $type
 * @property CarbonImmutable $occurrence_date
 * @property numeric|null $amount_override
 * @property CarbonImmutable|null $date_override
 * @property int|null $sort
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\PlannedIncome $plannedIncome
 * @method static \Database\Factories\PlannedIncomeExceptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|PlannedIncomeException newModelQuery()
 * @method static Builder<static>|PlannedIncomeException newQuery()
 * @method static Builder<static>|PlannedIncomeException query()
 * @mixin \Eloquent
 */
#[Fillable(['planned_income_id', 'type', 'occurrence_date', 'amount_override', 'date_override', 'sort', 'notes'])]
final class PlannedIncomeException extends DatoDellaFamiglia
{
    /** @use HasFactory<PlannedIncomeExceptionFactory> */
    use HasFactory;

    /**
     * L'entrata prevista a cui appartiene.
     *
     * @return BelongsTo<PlannedIncome, $this>
     */
    public function plannedIncome(): BelongsTo
    {
        return $this->belongsTo(PlannedIncome::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TipoEccezione::class,
            'occurrence_date' => 'date',
            'amount_override' => 'decimal:2',
            'date_override' => 'date',
            'sort' => 'integer',
        ];
    }
}
