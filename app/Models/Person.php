<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuoloPersona;
use Carbon\CarbonImmutable;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property-read Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\File|null $photo
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\PersonFactory factory($count = null, $state = [])
 * @method static Builder<static>|Person newModelQuery()
 * @method static Builder<static>|Person newQuery()
 * @method static Builder<static>|Person query()
 * @mixin \Eloquent
 */
#[Fillable(['first_name', 'last_name', 'initials', 'role', 'birth_date', 'tax_code', 'photo_id', 'user_id', 'notes'])]
final class Person extends DatoDellaFamiglia
{
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
     * I documenti della persona (`documents.person_id`).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * La foto della persona.
     *
     * @return BelongsTo<File, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(File::class);
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
