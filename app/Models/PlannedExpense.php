<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitaIntervallo;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una spesa prevista, una volta o ricorrente (`planned_expenses`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property numeric $amount
 * @property int|null $category_id
 * @property bool $is_recurring
 * @property int $interval
 * @property UnitaIntervallo $interval_unit
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property bool $is_estimated
 * @property bool $is_auto_created
 * @property int|null $organization_id
 * @property int|null $financial_account_id
 * @property int|null $person_id
 * @property int|null $utility_id
 * @property int|null $insurance_policy_id
 * @property int|null $loan_id
 * @property int|null $enrollment_id
 * @property int|null $home_id
 * @property int|null $vehicle_id
 * @property int|null $subscription_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ExpenseCategory|null $category
 * @property-read Enrollment|null $enrollment
 * @property-read Collection<int, PlannedExpenseException> $exceptions
 * @property-read int|null $exceptions_count
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Home|null $home
 * @property-read InsurancePolicy|null $insurancePolicy
 * @property-read Loan|null $loan
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 * @property-read Subscription|null $subscription
 * @property-read Utility|null $utility
 * @property-read Vehicle|null $vehicle
 *
 * @method static PlannedExpenseFactory factory($count = null, $state = [])
 * @method static Builder<static>|PlannedExpense newModelQuery()
 * @method static Builder<static>|PlannedExpense newQuery()
 * @method static Builder<static>|PlannedExpense query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'amount', 'category_id', 'is_recurring', 'interval', 'interval_unit', 'start_date', 'end_date', 'is_estimated', 'is_auto_created', 'organization_id', 'financial_account_id', 'person_id', 'utility_id', 'insurance_policy_id', 'loan_id', 'enrollment_id', 'home_id', 'vehicle_id', 'subscription_id', 'notes'])]
final class PlannedExpense extends DatoDellaFamiglia
{
    /** @use HasFactory<PlannedExpenseFactory> */
    use HasFactory;

    /**
     * La categoria.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

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
     * Il conto collegato.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
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
     * L'utenza collegata.
     *
     * @return BelongsTo<Utility, $this>
     */
    public function utility(): BelongsTo
    {
        return $this->belongsTo(Utility::class);
    }

    /**
     * La polizza collegata.
     *
     * @return BelongsTo<InsurancePolicy, $this>
     */
    public function insurancePolicy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class);
    }

    /**
     * Il finanziamento collegato.
     *
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * L'iscrizione collegata.
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * La casa collegata.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
    }

    /**
     * Il veicolo collegato.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * L'abbonamento collegato.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Le eccezioni delle singole scadenze.
     *
     * @return HasMany<PlannedExpenseException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(PlannedExpenseException::class, 'planned_expense_id');
    }

    /**
     * Le spese pagate per questa spesa prevista.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
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
            'is_recurring' => 'boolean',
            'interval' => 'integer',
            'interval_unit' => UnitaIntervallo::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_estimated' => 'boolean',
            'is_auto_created' => 'boolean',
        ];
    }
}
