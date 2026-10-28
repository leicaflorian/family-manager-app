<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HomeStatus;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\HomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A home of the family (`homes`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property HomeStatus $status
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property bool $is_owned
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Belonging> $belongings
 * @property-read int|null $belongings_count
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read Collection<int, InsurancePolicy> $insurancePolicies
 * @property-read int|null $insurance_policies_count
 * @property-read Collection<int, MaintenanceRecord> $maintenanceRecords
 * @property-read int|null $maintenance_records_count
 * @property-read Collection<int, Utility> $utilities
 * @property-read int|null $utilities_count
 *
 * @method static HomeFactory factory($count = null, $state = [])
 * @method static Builder<static>|Home newModelQuery()
 * @method static Builder<static>|Home newQuery()
 * @method static Builder<static>|Home query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'status', 'address', 'city', 'country', 'is_owned', 'notes'])]
final class Home extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<HomeFactory> */
    use HasFactory;

    /**
     * The utilities of the home.
     *
     * @return HasMany<Utility, $this>
     */
    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    /**
     * The belongings of the home.
     *
     * @return HasMany<Belonging, $this>
     */
    public function belongings(): HasMany
    {
        return $this->hasMany(Belonging::class);
    }

    /**
     * The maintenance jobs on the home.
     *
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * The insurance policies of the home.
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
            'status' => HomeStatus::class,
            'is_owned' => 'boolean',
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
