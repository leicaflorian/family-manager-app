<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vehicle of the family (`vehicles`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property VehicleStatus $status
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $plate_number
 * @property int|null $registration_year
 * @property bool $is_owned
 * @property int|null $person_id
 * @property CarbonImmutable|null $road_tax_due_date
 * @property CarbonImmutable|null $inspection_due_date
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
 * @property-read Person|null $person
 *
 * @method static VehicleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Vehicle newModelQuery()
 * @method static Builder<static>|Vehicle newQuery()
 * @method static Builder<static>|Vehicle query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'status', 'brand', 'model', 'plate_number', 'registration_year', 'is_owned', 'person_id', 'road_tax_due_date', 'inspection_due_date', 'notes'])]
final class Vehicle extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<VehicleFactory> */
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
     * The insurance policies of the vehicle.
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
            'status' => VehicleStatus::class,
            'is_owned' => 'boolean',
            'registration_year' => 'integer',
            'road_tax_due_date' => 'date',
            'inspection_due_date' => 'date',
        ];
    }
}
