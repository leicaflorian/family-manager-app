<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasExpenseLinks;
use App\Observers\ExpenseDeadlineObserver;
use App\Observers\ExpenseRenewalObserver;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property CarbonImmutable|null $occurrence_date The original due date of the planned item that this payment settles
 * @property int|null $category_id
 * @property int|null $organization_id
 * @property int|null $financial_account_id
 * @property int|null $person_id
 * @property string|null $card_type
 * @property int|null $card_id
 * @property numeric|null $fee_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model|null $card
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
#[Fillable(['title', 'amount', 'date', 'planned_expense_id', 'occurrence_date', 'category_id', 'organization_id', 'financial_account_id', 'person_id', 'card_type', 'card_id', 'fee_amount', 'notes'])]
#[ObservedBy([ExpenseRenewalObserver::class, ExpenseDeadlineObserver::class])]
final class Expense extends FamilyModel
{
    use HasDocuments;
    use HasExpenseLinks;

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
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'occurrence_date' => 'date',
            'fee_amount' => 'decimal:2',
            'card_id' => 'integer',
        ];
    }

    /**
     * The name among a document's links: the title.
     */
    public function label(): string
    {
        return $this->title;
    }
}
