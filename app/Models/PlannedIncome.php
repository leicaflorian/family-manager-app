<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEntrata;
use App\Enums\UnitaIntervallo;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedIncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un'entrata prevista, una volta o ricorrente (`planned_incomes`).
 *
 * @property int $id
 * @property int $family_id
 * @property string|null $title
 * @property numeric|null $amount
 * @property TipoEntrata $type
 * @property bool $is_recurring
 * @property int|null $interval
 * @property UnitaIntervallo $interval_unit
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property bool $is_estimated
 * @property int|null $organization_id
 * @property int|null $person_id
 * @property int|null $financial_account_id
 * @property int|null $employment_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Employment|null $employment
 * @property-read Collection<int, PlannedIncomeException> $exceptions
 * @property-read int|null $exceptions_count
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Collection<int, Income> $incomes
 * @property-read int|null $incomes_count
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static PlannedIncomeFactory factory($count = null, $state = [])
 * @method static Builder<static>|PlannedIncome newModelQuery()
 * @method static Builder<static>|PlannedIncome newQuery()
 * @method static Builder<static>|PlannedIncome query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'amount', 'type', 'is_recurring', 'interval', 'interval_unit', 'start_date', 'end_date', 'is_estimated', 'organization_id', 'person_id', 'financial_account_id', 'employment_id', 'notes'])]
final class PlannedIncome extends DatoDellaFamiglia
{
    /** @use HasFactory<PlannedIncomeFactory> */
    use HasFactory;

    /**
     * L'ente collegato.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * La persona a cui si riferisce.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Il conto collegato.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * Il lavoro collegato.
     *
     * @return BelongsTo<Employment, $this>
     */
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    /**
     * Le eccezioni delle singole scadenze.
     *
     * @return HasMany<PlannedIncomeException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(PlannedIncomeException::class, 'planned_income_id');
    }

    /**
     * Le entrate incassate per questa entrata prevista.
     *
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => TipoEntrata::class,
            'is_recurring' => 'boolean',
            'interval' => 'integer',
            'interval_unit' => UnitaIntervallo::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_estimated' => 'boolean',
        ];
    }
}
