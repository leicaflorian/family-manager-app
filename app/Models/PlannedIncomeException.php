<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExceptionType;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedIncomeExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exception of a planned income (`planned_income_exceptions`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $planned_income_id
 * @property ExceptionType $type
 * @property CarbonImmutable $occurrence_date
 * @property numeric|null $amount_override
 * @property CarbonImmutable|null $date_override
 * @property int|null $sort
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read PlannedIncome $plannedIncome
 *
 * @method static PlannedIncomeExceptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|PlannedIncomeException newModelQuery()
 * @method static Builder<static>|PlannedIncomeException newQuery()
 * @method static Builder<static>|PlannedIncomeException query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['planned_income_id', 'type', 'occurrence_date', 'amount_override', 'date_override', 'sort', 'notes'])]
final class PlannedIncomeException extends FamilyModel
{
    /** @use HasFactory<PlannedIncomeExceptionFactory> */
    use HasFactory;

    /**
     * The planned income it belongs to.
     *
     * @return BelongsTo<PlannedIncome, $this>
     */
    public function plannedIncome(): BelongsTo
    {
        return $this->belongsTo(PlannedIncome::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'occurrence_date' => 'date',
            'amount_override' => 'decimal:2',
            'date_override' => 'date',
            'sort' => 'integer',
        ];
    }
}
