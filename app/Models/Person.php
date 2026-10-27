<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonRole;
use Carbon\CarbonImmutable;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person of the family (`people` in Directus), with or without an account.
 *
 * @property int $id
 * @property int $family_id
 * @property string $first_name
 * @property string $last_name
 * @property string $initials
 * @property PersonRole $role
 * @property CarbonImmutable|null $birth_date
 * @property string|null $tax_code
 * @property int|null $photo_id
 * @property int|null $user_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read File|null $photo
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
final class Person extends FamilyModel
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * The account of the person, if they have one.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The documents of the person (`documents.person_id`).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The photo of the person.
     *
     * @return BelongsTo<File, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PersonRole::class,
            'birth_date' => 'date',
        ];
    }
}
