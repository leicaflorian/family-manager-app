<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una spesa pagata (`expenses`).
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
 * @property-read \App\Models\ExpenseCategory|null $category
 * @property-read Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\FinancialAccount|null $financialAccount
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\Person|null $person
 * @property-read \App\Models\PlannedExpense|null $plannedExpense
 * @method static \Database\Factories\ExpenseFactory factory($count = null, $state = [])
 * @method static Builder<static>|Expense newModelQuery()
 * @method static Builder<static>|Expense newQuery()
 * @method static Builder<static>|Expense query()
 * @mixin \Eloquent
 */
#[Fillable(['title', 'amount', 'date', 'planned_expense_id', 'category_id', 'organization_id', 'financial_account_id', 'person_id', 'fee_amount', 'notes'])]
final class Expense extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<ExpenseFactory> */
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
     * I tipi delle colonne.
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
