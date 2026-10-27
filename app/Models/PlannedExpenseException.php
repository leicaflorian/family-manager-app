<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEccezione;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedExpenseExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un'eccezione di una spesa prevista: saltata, con un altro importo o un'altra data (`planned_expense_exceptions`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $planned_expense_id
 * @property TipoEccezione $type
 * @property CarbonImmutable|null $occurrence_date
 * @property numeric|null $amount_override
 * @property CarbonImmutable|null $date_override
 * @property int|null $sort
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\PlannedExpense $plannedExpense
 * @method static \Database\Factories\PlannedExpenseExceptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|PlannedExpenseException newModelQuery()
 * @method static Builder<static>|PlannedExpenseException newQuery()
 * @method static Builder<static>|PlannedExpenseException query()
 * @mixin \Eloquent
 */
#[Fillable(['planned_expense_id', 'type', 'occurrence_date', 'amount_override', 'date_override', 'sort', 'notes'])]
final class PlannedExpenseException extends DatoDellaFamiglia
{
    /** @use HasFactory<PlannedExpenseExceptionFactory> */
    use HasFactory;

    /**
     * La spesa prevista a cui appartiene.
     *
     * @return BelongsTo<PlannedExpense, $this>
     */
    public function plannedExpense(): BelongsTo
    {
        return $this->belongsTo(PlannedExpense::class);
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
