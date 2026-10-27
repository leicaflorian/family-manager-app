<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoUtenza;
use App\Enums\TipoUtenza;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\UtilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un'utenza: luce, gas, acqua, internet, telefono, TARI, canone RAI (`utilities`).
 *
 * @property int $id
 * @property int $family_id
 * @property TipoUtenza $type
 * @property StatoUtenza $status
 * @property int|null $organization_id
 * @property int|null $home_id
 * @property int|null $person_id
 * @property string|null $customer_code
 * @property string|null $supply_point_code
 * @property CarbonImmutable|null $activation_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\Home|null $home
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\Person|null $person
 * @method static \Database\Factories\UtilityFactory factory($count = null, $state = [])
 * @method static Builder<static>|Utility newModelQuery()
 * @method static Builder<static>|Utility newQuery()
 * @method static Builder<static>|Utility query()
 * @mixin \Eloquent
 */
#[Fillable(['type', 'status', 'organization_id', 'home_id', 'person_id', 'customer_code', 'supply_point_code', 'activation_date', 'notes'])]
final class Utility extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<UtilityFactory> */
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
     * La casa collegata.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
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
            'type' => TipoUtenza::class,
            'status' => StatoUtenza::class,
            'activation_date' => 'date',
        ];
    }
}
