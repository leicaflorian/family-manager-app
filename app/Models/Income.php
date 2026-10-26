<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEntrata;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\IncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un'entrata incassata (`incomes`).
 *
 * @property int $id
 * @property int $family_id
 * @property string|null $title
 * @property numeric|null $amount
 * @property CarbonImmutable $date
 * @property TipoEntrata $type
 * @property int|null $planned_income_id
 * @property int|null $organization_id
 * @property int|null $person_id
 * @property int|null $financial_account_id
 * @property int|null $employment_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Employment|null $employment
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 * @property-read PlannedIncome|null $plannedIncome
 *
 * @method static IncomeFactory factory($count = null, $state = [])
 * @method static Builder<static>|Income newModelQuery()
 * @method static Builder<static>|Income newQuery()
 * @method static Builder<static>|Income query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'amount', 'date', 'type', 'planned_income_id', 'organization_id', 'person_id', 'financial_account_id', 'employment_id', 'notes'])]
final class Income extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<IncomeFactory> */
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
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'type' => TipoEntrata::class,
        ];
    }
}
