<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoCasa;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\HomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una casa della famiglia (`homes`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property StatoCasa $status
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property bool $is_owned
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, \App\Models\Belonging> $belongings
 * @property-read int|null $belongings_count
 * @property-read Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Family $family
 * @property-read Collection<int, \App\Models\Utility> $utilities
 * @property-read int|null $utilities_count
 * @method static \Database\Factories\HomeFactory factory($count = null, $state = [])
 * @method static Builder<static>|Home newModelQuery()
 * @method static Builder<static>|Home newQuery()
 * @method static Builder<static>|Home query()
 * @mixin \Eloquent
 */
#[Fillable(['name', 'status', 'address', 'city', 'country', 'is_owned', 'notes'])]
final class Home extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<HomeFactory> */
    use HasFactory;

    /**
     * Le utenze della casa.
     *
     * @return HasMany<Utility, $this>
     */
    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    /**
     * I beni della casa.
     *
     * @return HasMany<Belonging, $this>
     */
    public function belongings(): HasMany
    {
        return $this->hasMany(Belonging::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatoCasa::class,
            'is_owned' => 'boolean',
        ];
    }
}
