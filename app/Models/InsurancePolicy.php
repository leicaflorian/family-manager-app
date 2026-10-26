<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoPolizza;
use App\Enums\TipoPolizza;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\InsurancePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una polizza o un fondo (`insurance_policies`).
 *
 * @property int $id
 * @property int $family_id
 * @property string|null $title
 * @property TipoPolizza $type
 * @property StatoPolizza $status
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
final class InsurancePolicy extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<InsurancePolicyFactory> */
    use HasFactory;

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
     * Il veicolo collegato.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
     * La casa collegata.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TipoPolizza::class,
            'status' => StatoPolizza::class,
            'start_date' => 'date',
            'expiry_date' => 'date',
        ];
    }
}
