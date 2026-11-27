<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Observers\DeadlineObserver;
use App\Support\Linkables;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A document with its file, linked to the records through `documentables`.
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property int $file_id
 * @property int|null $category_id
 * @property DocumentStatus $status
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
 * @property CarbonImmutable|null $pinned_at
 * @property int|null $pinned_by
 * @property bool $is_favorite Whether it is a favourite in the drawings gallery
 * @property-read Collection<int, Belonging> $belongings
 * @property-read int|null $belongings_count
 * @property-read DocumentCategory|null $category
 * @property-read UtilityConsumption|null $consumption
 * @property-read Collection<int, UtilityConsumption> $consumptions
 * @property-read int|null $consumptions_count
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
 * @property-read User|null $pinner
 * @property-read PlannedExpense|null $plannedExpense
 * @property-read Collection<int, PlannedExpense> $waitingPlans
 * @property-read int|null $waiting_plans_count
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
 * @method static Builder<static>|Document waitingFor(list<int> $plannedIds)
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'file_id', 'category_id', 'status', 'person_id', 'organization_id', 'planned_expense_id', 'date', 'expiry_date', 'tags', 'notes'])]
#[ObservedBy(DeadlineObserver::class)]
final class Document extends FamilyModel
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Pinning follows the person: when the document moves to another person it is no longer pinned.
     */
    protected static function booted(): void
    {
        self::updating(static function (self $document): void {
            if ($document->isDirty('person_id')) {
                $document->pinned_at = null;
                $document->pinned_by = null;
            }
        });
    }

    /**
     * The file of the document.
     *
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * The consumption row read from this bill, if any (a row written by hand has no document once the bill is gone).
     *
     * @return HasOne<UtilityConsumption, $this>
     */
    public function consumption(): HasOne
    {
        return $this->hasOne(UtilityConsumption::class);
    }

    /**
     * The consumption rows read from this bill: one per utility (a bill can carry electricity and gas).
     *
     * @return HasMany<UtilityConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(UtilityConsumption::class);
    }

    /**
     * The category of the document.
     *
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
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
     * The account that pinned it at the top of the person.
     *
     * @return BelongsTo<User, $this>
     */
    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    /**
     * Whether it belongs only to its person: it has a person and is not linked to any record.
     *
     * Reads the `is_linked` attribute when `withLinked()` or `markLinked()` put it there
     * (lists must not ask once per row), and asks the database otherwise.
     */
    public function isPersonal(): bool
    {
        if ($this->person_id === null) {
            return false;
        }

        if (array_key_exists('is_linked', $this->attributes)) {
            return ! (bool) $this->attributes['is_linked'];
        }

        return ! DB::table('documentables')->where('document_id', $this->id)->exists();
    }

    /**
     * Whether it counts as pinned at the top of its person: pinned and still personal.
     *
     * `pinned_at` stays on a document that gets linked to a record (it is only hidden from
     * the pinned list until unlinked), so a linked one must not look pinned.
     */
    public function isPinned(): bool
    {
        return $this->pinned_at !== null && $this->isPersonal();
    }

    /**
     * Adds `is_linked`: whether the document is linked to any record, in the same query.
     *
     * @param  Builder<Document>  $query  the query
     */
    #[Scope]
    protected function withLinked(Builder $query): void
    {
        $query->addSelect('documents.*')
            ->selectRaw('exists (select 1 from documentables where documentables.document_id = documents.id) as is_linked');
    }

    /**
     * Sets `is_linked` on documents already found, with one query for all of them.
     *
     * @param  Collection<int, Document>  $documents  the documents
     */
    public static function markLinked(Collection $documents): void
    {
        $documents = $documents->reject(static fn (self $document): bool => array_key_exists('is_linked', $document->attributes));
        if ($documents->isEmpty()) {
            return;
        }

        $linked = DB::table('documentables')->whereIn('document_id', $documents->pluck('id'))->distinct()->pluck('document_id')->flip();
        foreach ($documents as $document) {
            $document->setLinked($linked->has($document->id));
        }
    }

    /**
     * Sets `is_linked` as read-only information: it is not a column, so it must never count as a change to save.
     *
     * @param  bool  $linked  whether the document is linked to any record
     */
    public function setLinked(bool $linked): void
    {
        $this->setAttribute('is_linked', $linked);
        $this->syncOriginalAttribute('is_linked');
    }

    /**
     * The organization that sent it or that it refers to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The planned expense it refers to (a bill to pay).
     *
     * @return BelongsTo<PlannedExpense, $this>
     */
    public function plannedExpense(): BelongsTo
    {
        return $this->belongsTo(PlannedExpense::class);
    }

    /**
     * The other planned expenses it waits on, besides the one in `planned_expense_id` (a bill of electricity and gas).
     *
     * @return BelongsToMany<PlannedExpense, $this>
     */
    public function waitingPlans(): BelongsToMany
    {
        return $this->belongsToMany(PlannedExpense::class, 'document_planned_expense');
    }

    /**
     * Makes the document wait on a planned expense: in the column when it waits on nothing yet, in the table when it
     * already waits on another one. Doing it twice for the same planned expense changes nothing.
     *
     * @param  PlannedExpense  $planned  The planned expense it waits on.
     *
     * @throws LogicException When the planned expense belongs to another family.
     */
    public function waitOn(PlannedExpense $planned): void
    {
        if ($planned->family_id !== $this->family_id) {
            throw new LogicException("Document {$this->id} cannot wait on planned expense {$planned->id} of another family.");
        }

        if ($this->planned_expense_id === null) {
            $this->planned_expense_id = $planned->id;
            $this->save();

            return;
        }

        if ($this->planned_expense_id !== $planned->id) {
            $this->waitingPlans()->syncWithoutDetaching([$planned->id]);
        }
    }

    /**
     * Keeps the documents waiting on one of the planned expenses, from the column or from the table.
     *
     * @param  Builder<Document>  $query  the query
     * @param  list<int>  $plannedIds  ids of the planned expenses
     */
    #[Scope]
    protected function waitingFor(Builder $query, array $plannedIds): void
    {
        $query->where(static function (Builder $query) use ($plannedIds): void {
            $query->whereIn('documents.planned_expense_id', $plannedIds)
                ->orWhereIn('documents.id', DB::table('document_planned_expense')->select('document_id')->whereIn('planned_expense_id', $plannedIds));
        });
    }

    /**
     * The linked homes.
     *
     * @return MorphToMany<Home, $this>
     */
    public function homes(): MorphToMany
    {
        return $this->morphedByMany(Home::class, 'documentable');
    }

    /**
     * The linked utilities.
     *
     * @return MorphToMany<Utility, $this>
     */
    public function utilities(): MorphToMany
    {
        return $this->morphedByMany(Utility::class, 'documentable');
    }

    /**
     * The linked belongings.
     *
     * @return MorphToMany<Belonging, $this>
     */
    public function belongings(): MorphToMany
    {
        return $this->morphedByMany(Belonging::class, 'documentable');
    }

    /**
     * The linked maintenance records.
     *
     * @return MorphToMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): MorphToMany
    {
        return $this->morphedByMany(MaintenanceRecord::class, 'documentable');
    }

    /**
     * The linked vehicles.
     *
     * @return MorphToMany<Vehicle, $this>
     */
    public function vehicles(): MorphToMany
    {
        return $this->morphedByMany(Vehicle::class, 'documentable');
    }

    /**
     * The linked insurance policies.
     *
     * @return MorphToMany<InsurancePolicy, $this>
     */
    public function insurancePolicies(): MorphToMany
    {
        return $this->morphedByMany(InsurancePolicy::class, 'documentable');
    }

    /**
     * The linked financial accounts.
     *
     * @return MorphToMany<FinancialAccount, $this>
     */
    public function financialAccounts(): MorphToMany
    {
        return $this->morphedByMany(FinancialAccount::class, 'documentable');
    }

    /**
     * The linked loans.
     *
     * @return MorphToMany<Loan, $this>
     */
    public function loans(): MorphToMany
    {
        return $this->morphedByMany(Loan::class, 'documentable');
    }

    /**
     * The linked filings.
     *
     * @return MorphToMany<Filing, $this>
     */
    public function filings(): MorphToMany
    {
        return $this->morphedByMany(Filing::class, 'documentable');
    }

    /**
     * The linked employments.
     *
     * @return MorphToMany<Employment, $this>
     */
    public function employments(): MorphToMany
    {
        return $this->morphedByMany(Employment::class, 'documentable');
    }

    /**
     * The linked health events.
     *
     * @return MorphToMany<HealthEvent, $this>
     */
    public function healthEvents(): MorphToMany
    {
        return $this->morphedByMany(HealthEvent::class, 'documentable');
    }

    /**
     * The linked enrollments.
     *
     * @return MorphToMany<Enrollment, $this>
     */
    public function enrollments(): MorphToMany
    {
        return $this->morphedByMany(Enrollment::class, 'documentable');
    }

    /**
     * The linked subscriptions.
     *
     * @return MorphToMany<Subscription, $this>
     */
    public function subscriptions(): MorphToMany
    {
        return $this->morphedByMany(Subscription::class, 'documentable');
    }

    /**
     * The linked paid expenses.
     *
     * @return MorphToMany<Expense, $this>
     */
    public function expenses(): MorphToMany
    {
        return $this->morphedByMany(Expense::class, 'documentable');
    }

    /**
     * The linked incomes.
     *
     * @return MorphToMany<Income, $this>
     */
    public function incomes(): MorphToMany
    {
        return $this->morphedByMany(Income::class, 'documentable');
    }

    /**
     * All the linked records, of every type, in a single list.
     *
     * @return Collection<int, Model>
     */
    public function linkables(): Collection
    {
        $records = new Collection;

        foreach (Linkables::WITH_DOCUMENTS as $relation) {
            /** @var Collection<int, Model> $linked */
            $linked = $this->getRelationValue($relation);
            $records = $records->concat($linked);
        }

        return $records->values();
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'date' => 'date',
            'expiry_date' => 'date',
            'tags' => 'array',
            'is_favorite' => 'boolean',
            'pinned_at' => 'immutable_datetime',
        ];
    }
}
