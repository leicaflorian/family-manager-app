<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Observers\LoanPlanObserver;
use App\Observers\PlannedExpenseObserver;
use Carbon\CarbonImmutable;
use Database\Factories\PlannedExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A planned expense, one-off or recurring (`planned_expenses`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property numeric $amount
 * @property int|null $category_id
 * @property bool $is_recurring
 * @property int $interval
 * @property IntervalUnit $interval_unit
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property bool $is_estimated
 * @property bool $is_auto_created
 * @property int|null $organization_id
 * @property int|null $financial_account_id
 * @property int|null $person_id
 * @property string|null $card_type
 * @property int|null $card_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model|null $card
 * @property-read ExpenseCategory|null $category
 * @property-read Collection<int, PlannedExpenseException> $exceptions
 * @property-read int|null $exceptions_count
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static PlannedExpenseFactory factory($count = null, $state = [])
 * @method static Builder<static>|PlannedExpense newModelQuery()
 * @method static Builder<static>|PlannedExpense newQuery()
 * @method static Builder<static>|PlannedExpense query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'amount', 'category_id', 'is_recurring', 'interval', 'interval_unit', 'start_date', 'end_date', 'is_estimated', 'is_auto_created', 'organization_id', 'financial_account_id', 'person_id', 'card_type', 'card_id', 'notes'])]
#[ObservedBy([LoanPlanObserver::class, PlannedExpenseObserver::class])]
final class PlannedExpense extends FamilyModel
{
    /** @use HasFactory<PlannedExpenseFactory> */
    use HasFactory;

    /**
     * The category.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * The linked organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The linked financial account.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * The person it refers to.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The card it refers to (a utility, a vehicle, a loan…), if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function card(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The exceptions of single due dates.
     *
     * @return HasMany<PlannedExpenseException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(PlannedExpenseException::class, 'planned_expense_id');
    }

    /**
     * The expenses paid for this planned expense.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * What to call it: its title, which a planned expense always has. {@see PlannedIncome::displayTitle()} is the same for an income.
     */
    public function displayTitle(): string
    {
        return $this->title;
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
            'interval' => 'integer',
            'interval_unit' => IntervalUnit::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_estimated' => 'boolean',
            'is_auto_created' => 'boolean',
            'card_id' => 'integer',
        ];
    }
}
