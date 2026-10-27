<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoanStatus;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A loan or a mortgage (`loans`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property LoanStatus $status
 * @property int|null $organization_id
 * @property int|null $person_id
 * @property int|null $financial_account_id
 * @property numeric|null $total_amount
 * @property int|null $installments_count
 * @property numeric|null $installment_amount
 * @property numeric|null $final_installment_amount
 * @property numeric|null $total_to_pay
 * @property numeric|null $cost
 * @property int|null $installments_paid
 * @property int|null $installments_left
 * @property numeric|null $residual_amount
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static LoanFactory factory($count = null, $state = [])
 * @method static Builder<static>|Loan newModelQuery()
 * @method static Builder<static>|Loan newQuery()
 * @method static Builder<static>|Loan query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'status', 'organization_id', 'person_id', 'financial_account_id', 'total_amount', 'installments_count', 'installment_amount', 'final_installment_amount', 'total_to_pay', 'cost', 'installments_paid', 'installments_left', 'residual_amount', 'start_date', 'end_date', 'notes'])]
final class Loan extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<LoanFactory> */
    use HasFactory;

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
     * The person it refers to.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'total_amount' => 'decimal:2',
            'installments_count' => 'integer',
            'installment_amount' => 'decimal:2',
            'final_installment_amount' => 'decimal:2',
            'total_to_pay' => 'decimal:2',
            'cost' => 'decimal:2',
            'installments_paid' => 'integer',
            'installments_left' => 'integer',
            'residual_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the name.
     */
    public function label(): string
    {
        return $this->name;
    }
}
