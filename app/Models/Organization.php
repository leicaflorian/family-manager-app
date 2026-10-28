<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationType;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * An organization: provider, bank, school, doctor, office (`organizations`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property string|null $icon
 * @property Collection<int, OrganizationType>|null $types
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property int|null $logo_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read File|null $logo
 *
 * @method static OrganizationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Organization newModelQuery()
 * @method static Builder<static>|Organization newQuery()
 * @method static Builder<static>|Organization query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'icon', 'types', 'email', 'phone', 'website', 'logo_id', 'notes'])]
final class Organization extends FamilyModel
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * The logo of the organization.
     *
     * @return BelongsTo<File, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * The address of the logo of the organization, or null without one. Load `logo` first in a list.
     */
    public function logoUrl(): ?string
    {
        return $this->logo?->imageUrl();
    }

    /**
     * The documents of the organization (`documents.organization_id`).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The values of the types, in the order they were chosen.
     *
     * @return list<string>
     */
    public function typeValues(): array
    {
        return array_values(($this->types ?? collect())->map(static fn (OrganizationType $type): string => $type->value)->all());
    }

    /**
     * The labels of the types, in the language of the page.
     *
     * @return list<string>
     */
    public function typeLabels(): array
    {
        return array_values(($this->types ?? collect())->map(static fn (OrganizationType $type): string => $type->label())->all());
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'types' => AsEnumCollection::of(OrganizationType::class),
        ];
    }
}
