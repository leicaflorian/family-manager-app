<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FamilyState;
use Carbon\CarbonImmutable;
use Database\Factories\FamilyFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * A family: every piece of data in the app belongs to one.
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $welcomed_at
 * @property string|null $calendar_token The secret of the calendar address, encrypted; null while the calendar is off
 * @property string|null $calendar_token_hash The sha256 of the secret, to find the family from the address
 * @property string|null $calendar_locale The language of the texts of the calendar, en or it: the one of the page it was turned on in
 * @property CarbonImmutable|null $copied_from_directus_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $delete_after
 * @property CarbonImmutable|null $support_until
 * @property int|null $reading_limit Readings a month for this family; empty means the limit of everyone
 * @property-read Collection<int, Person> $people
 * @property-read int|null $people_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static FamilyFactory factory($count = null, $state = [])
 * @method static Builder<static>|Family newModelQuery()
 * @method static Builder<static>|Family newQuery()
 * @method static Builder<static>|Family query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'welcomed_at'])]
#[Hidden(['calendar_token', 'calendar_token_hash'])]
final class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory;

    /**
     * The accounts of the family.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The people of the family, with or without an account. It goes through the
     * current family scope, like every read of people.
     *
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * Whether the family has finished the *Welcome*.
     */
    public function isWelcomed(): bool
    {
        return $this->welcomed_at !== null;
    }

    /**
     * The secret of the calendar address, decrypted.
     *
     * @return string|null the secret; null while the calendar is off, or when it was encrypted with another `APP_KEY`
     *                     and cannot be read any more (then the calendar is still on: `calendar_token_hash` is set)
     */
    public function calendarSecret(): ?string
    {
        $encrypted = $this->getAttributes()['calendar_token'] ?? null;

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Where the family is: waiting to be deleted wins over suspended.
     */
    public function state(): FamilyState
    {
        return match (true) {
            $this->delete_after !== null => FamilyState::ToDelete,
            $this->suspended_at !== null => FamilyState::Suspended,
            default => FamilyState::Active,
        };
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'welcomed_at' => 'datetime',
            'copied_from_directus_at' => 'datetime',
            'suspended_at' => 'datetime',
            'delete_after' => 'datetime',
            'support_until' => 'datetime',
            'reading_limit' => 'integer',
            'calendar_token' => 'encrypted',
        ];
    }
}
