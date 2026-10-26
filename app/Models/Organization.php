<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEnte;
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
 * Un ente: fornitore, banca, scuola, medico, ufficio (`organizations`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property string|null $icon
 * @property Collection<int, TipoEnte>|null $types
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
final class Organization extends DatoDellaFamiglia
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Il logo dell'ente.
     *
     * @return BelongsTo<File, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * I documenti dell'ente (`documents.organization_id`).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'types' => AsEnumCollection::of(TipoEnte::class),
        ];
    }
}
