<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\EmploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's employment (`employments`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $person_id
 * @property int|null $organization_id
 * @property string|null $job_title
 * @property EmploymentStatus $status
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
 * @property-read Collection<int, InsurancePolicy> $insurancePolicies
 * @property-read int|null $insurance_policies_count
 * @property-read Organization|null $organization
 * @property-read Person $person
 *
 * @method static EmploymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Employment newModelQuery()
 * @method static Builder<static>|Employment newQuery()
 * @method static Builder<static>|Employment query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['person_id', 'organization_id', 'job_title', 'status', 'start_date', 'end_date', 'notes'])]
final class Employment extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<EmploymentFactory> */
    use HasFactory;

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
     * The linked organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The insurance policies that come with the employment: health, accident, fund.
     *
     * @return HasMany<InsurancePolicy, $this>
     */
    public function insurancePolicies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmploymentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the person and the employer, for example «Laura - Comune di Bologna».
     */
    public function label(): string
    {
        return self::joinLabel($this->person->first_name, $this->organization?->name);
    }

    /**
     * The relations the name reads.
     *
     * @return list<string>
     */
    public static function labelRelations(): array
    {
        return ['person', 'organization'];
    }
}
