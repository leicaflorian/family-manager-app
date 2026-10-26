<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoDocumento;
use App\Support\Schede;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Un documento con il suo file, collegato alle schede da `documentables`.
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property int $file_id
 * @property int|null $category_id
 * @property StatoDocumento $status
 * @property int|null $person_id
 * @property int|null $organization_id
 * @property int|null $planned_expense_id
 * @property CarbonImmutable|null $date
 * @property CarbonImmutable|null $expiry_date
 * @property array<array-key, mixed>|null $tags
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Belonging> $belongings
 * @property-read int|null $belongings_count
 * @property-read DocumentCategory|null $category
 * @property-read Collection<int, Employment> $employments
 * @property-read int|null $employments_count
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Family $family
 * @property-read File $file
 * @property-read Collection<int, Filing> $filings
 * @property-read int|null $filings_count
 * @property-read Collection<int, FinancialAccount> $financialAccounts
 * @property-read int|null $financial_accounts_count
 * @property-read Collection<int, HealthEvent> $healthEvents
 * @property-read int|null $health_events_count
 * @property-read Collection<int, Home> $homes
 * @property-read int|null $homes_count
 * @property-read Collection<int, Income> $incomes
 * @property-read int|null $incomes_count
 * @property-read Collection<int, InsurancePolicy> $insurancePolicies
 * @property-read int|null $insurance_policies_count
 * @property-read Collection<int, Loan> $loans
 * @property-read int|null $loans_count
 * @property-read Collection<int, MaintenanceRecord> $maintenanceRecords
 * @property-read int|null $maintenance_records_count
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 * @property-read PlannedExpense|null $plannedExpense
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read Collection<int, Utility> $utilities
 * @property-read int|null $utilities_count
 * @property-read Collection<int, Vehicle> $vehicles
 * @property-read int|null $vehicles_count
 *
 * @method static DocumentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Document newModelQuery()
 * @method static Builder<static>|Document newQuery()
 * @method static Builder<static>|Document query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'file_id', 'category_id', 'status', 'person_id', 'organization_id', 'planned_expense_id', 'date', 'expiry_date', 'tags', 'notes'])]
final class Document extends DatoDellaFamiglia
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Il file del documento.
     *
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * La categoria del documento.
     *
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
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
     * L'ente che lo ha mandato o a cui si riferisce.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * La spesa prevista a cui si riferisce (una bolletta da pagare).
     *
     * @return BelongsTo<PlannedExpense, $this>
     */
    public function plannedExpense(): BelongsTo
    {
        return $this->belongsTo(PlannedExpense::class);
    }

    /**
     * Le case collegate.
     *
     * @return MorphToMany<Home, $this>
     */
    public function homes(): MorphToMany
    {
        return $this->morphedByMany(Home::class, 'documentable');
    }

    /**
     * Le utenze collegate.
     *
     * @return MorphToMany<Utility, $this>
     */
    public function utilities(): MorphToMany
    {
        return $this->morphedByMany(Utility::class, 'documentable');
    }

    /**
     * I beni collegati.
     *
     * @return MorphToMany<Belonging, $this>
     */
    public function belongings(): MorphToMany
    {
        return $this->morphedByMany(Belonging::class, 'documentable');
    }

    /**
     * Le manutenzioni collegate.
     *
     * @return MorphToMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): MorphToMany
    {
        return $this->morphedByMany(MaintenanceRecord::class, 'documentable');
    }

    /**
     * I veicoli collegati.
     *
     * @return MorphToMany<Vehicle, $this>
     */
    public function vehicles(): MorphToMany
    {
        return $this->morphedByMany(Vehicle::class, 'documentable');
    }

    /**
     * Le polizze collegate.
     *
     * @return MorphToMany<InsurancePolicy, $this>
     */
    public function insurancePolicies(): MorphToMany
    {
        return $this->morphedByMany(InsurancePolicy::class, 'documentable');
    }

    /**
     * I conti collegati.
     *
     * @return MorphToMany<FinancialAccount, $this>
     */
    public function financialAccounts(): MorphToMany
    {
        return $this->morphedByMany(FinancialAccount::class, 'documentable');
    }

    /**
     * I finanziamenti collegati.
     *
     * @return MorphToMany<Loan, $this>
     */
    public function loans(): MorphToMany
    {
        return $this->morphedByMany(Loan::class, 'documentable');
    }

    /**
     * Le pratiche collegate.
     *
     * @return MorphToMany<Filing, $this>
     */
    public function filings(): MorphToMany
    {
        return $this->morphedByMany(Filing::class, 'documentable');
    }

    /**
     * I lavori collegati.
     *
     * @return MorphToMany<Employment, $this>
     */
    public function employments(): MorphToMany
    {
        return $this->morphedByMany(Employment::class, 'documentable');
    }

    /**
     * Gli eventi di salute collegati.
     *
     * @return MorphToMany<HealthEvent, $this>
     */
    public function healthEvents(): MorphToMany
    {
        return $this->morphedByMany(HealthEvent::class, 'documentable');
    }

    /**
     * Le iscrizioni collegate.
     *
     * @return MorphToMany<Enrollment, $this>
     */
    public function enrollments(): MorphToMany
    {
        return $this->morphedByMany(Enrollment::class, 'documentable');
    }

    /**
     * Gli abbonamenti collegati.
     *
     * @return MorphToMany<Subscription, $this>
     */
    public function subscriptions(): MorphToMany
    {
        return $this->morphedByMany(Subscription::class, 'documentable');
    }

    /**
     * Le spese pagate collegate.
     *
     * @return MorphToMany<Expense, $this>
     */
    public function expenses(): MorphToMany
    {
        return $this->morphedByMany(Expense::class, 'documentable');
    }

    /**
     * Le entrate collegate.
     *
     * @return MorphToMany<Income, $this>
     */
    public function incomes(): MorphToMany
    {
        return $this->morphedByMany(Income::class, 'documentable');
    }

    /**
     * Tutte le schede collegate, di ogni tipo, in un elenco solo.
     *
     * @return Collection<int, Model>
     */
    public function linkables(): Collection
    {
        $schede = new Collection;

        foreach (Schede::CON_DOCUMENTI as $relazione) {
            /** @var Collection<int, Model> $collegate */
            $collegate = $this->getRelationValue($relazione);
            $schede = $schede->concat($collegate);
        }

        return $schede->values();
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatoDocumento::class,
            'date' => 'date',
            'expiry_date' => 'date',
            'tags' => 'array',
        ];
    }
}
