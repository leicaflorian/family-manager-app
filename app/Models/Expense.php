<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid expense (`expenses`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property numeric $amount
 * @property CarbonImmutable|null $date
 * @property int|null $planned_expense_id
 * @property int|null $category_id
 * @property int|null $organization_id
 * @property int|null $financial_account_id
 * @property int|null $person_id
 * @property numeric|null $fee_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ExpenseCategory|null $category
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 * @property-read PlannedExpense|null $plannedExpense
 *
 * @method static ExpenseFactory factory($count = null, $state = [])
 * @method static Builder<static>|Expense newModelQuery()
 * @method static Builder<static>|Expense newQuery()
 * @method static Builder<static>|Expense query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'amount', 'date', 'planned_expense_id', 'category_id', 'organization_id', 'financial_account_id', 'person_id', 'fee_amount', 'notes'])]
final class Expense extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * The planned expense it belongs to.
     *
     * @return BelongsTo<PlannedExpense, $this>
     */
    public function plannedExpense(): BelongsTo
    {
        return $this->belongsTo(PlannedExpense::class);
    }

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
     * L'ente collegato.
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
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'fee_amount' => 'decimal:2',
        ];
    }
}
