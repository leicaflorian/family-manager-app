<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuoloPersona;
use App\Models\Concerns\BelongsToFamily;
use Carbon\CarbonImmutable;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una persona della famiglia (`people` di Directus), con o senza un account.
 *
 * @property int $id
 * @property int $family_id
 * @property string $first_name
 * @property string $last_name
 * @property string $initials
 * @property RuoloPersona $role
 * @property CarbonImmutable|null $birth_date
 * @property string|null $tax_code
 * @property int|null $photo_id
 * @property int|null $user_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read User|null $user
 *
 * @method static PersonFactory factory($count = null, $state = [])
 * @method static Builder<static>|Person newModelQuery()
 * @method static Builder<static>|Person newQuery()
 * @method static Builder<static>|Person query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['first_name', 'last_name', 'initials', 'role', 'birth_date', 'tax_code', 'photo_id', 'user_id', 'notes'])]
final class Person extends Model
{
    use BelongsToFamily;

    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * L'account della persona, se ne ha uno.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => RuoloPersona::class,
            'birth_date' => 'date',
        ];
    }
}
