<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InsurancePolicyStatus;
use App\Enums\InsurancePolicyType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\InsurancePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An insurance policy or a fund (`insurance_policies`).
 *
 * @property int $id
 * @property int $family_id
 * @property string|null $title
 * @property InsurancePolicyType $type
 * @property InsurancePolicyStatus $status
 * @property string|null $policy_number
 * @property int|null $organization_id
 * @property int|null $person_id
 * @property int|null $vehicle_id
 * @property int|null $employment_id
 * @property int|null $home_id
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $expiry_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Employment|null $employment
 * @property-read Family $family
 * @property-read Home|null $home
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 * @property-read Vehicle|null $vehicle
 *
 * @method static InsurancePolicyFactory factory($count = null, $state = [])
 * @method static Builder<static>|InsurancePolicy newModelQuery()
 * @method static Builder<static>|InsurancePolicy newQuery()
 * @method static Builder<static>|InsurancePolicy query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'type', 'status', 'policy_number', 'organization_id', 'person_id', 'vehicle_id', 'employment_id', 'home_id', 'start_date', 'expiry_date', 'notes'])]
final class InsurancePolicy extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<InsurancePolicyFactory> */
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
     * The linked vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The linked employment.
     *
     * @return BelongsTo<Employment, $this>
     */
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    /**
     * The linked home.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InsurancePolicyType::class,
            'status' => InsurancePolicyStatus::class,
            'start_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the title, or the type and the company.
     */
    public function label(): string
    {
        return $this->title ?? self::joinLabel($this->type->label(), $this->organization?->name);
    }

    /**
     * The relations the name reads.
     *
     * @return list<string>
     */
    public static function labelRelations(): array
    {
        return ['organization'];
    }
}
