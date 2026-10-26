<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una categoria dei documenti, con le sue sottocategorie (`document_categories`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property int|null $parent_id
 * @property string|null $icon
 * @property string|null $color
 * @property int|null $sort
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, DocumentCategory> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read DocumentCategory|null $parent
 *
 * @method static DocumentCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|DocumentCategory newModelQuery()
 * @method static Builder<static>|DocumentCategory newQuery()
 * @method static Builder<static>|DocumentCategory query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'parent_id', 'icon', 'color', 'sort'])]
final class DocumentCategory extends DatoDellaFamiglia
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    /**
     * La categoria madre, se questa è una sottocategoria.
     *
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    /**
     * Le sottocategorie.
     *
     * @return HasMany<DocumentCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DocumentCategory::class, 'parent_id');
    }

    /**
     * I documenti della categoria, senza quelli delle sottocategorie.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
