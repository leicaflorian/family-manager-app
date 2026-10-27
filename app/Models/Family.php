<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una famiglia: ogni dato dell'app appartiene a una di loro.
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $welcomed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, \App\Models\Person> $people
 * @property-read int|null $people_count
 * @property-read Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\FamilyFactory factory($count = null, $state = [])
 * @method static Builder<static>|Family newModelQuery()
 * @method static Builder<static>|Family newQuery()
 * @method static Builder<static>|Family query()
 * @mixin \Eloquent
 */
#[Fillable(['name', 'welcomed_at'])]
final class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory;

    /**
     * Gli account della famiglia.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Le persone della famiglia, con o senza account. Passa dallo scope della
     * famiglia corrente, come ogni lettura delle persone.
     *
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * La famiglia ha finito il *Benvenuto*.
     */
    public function accolta(): bool
    {
        return $this->welcomed_at !== null;
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['welcomed_at' => 'datetime'];
    }
}
