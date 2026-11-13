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
 * A document category, with its subcategories (`document_categories`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property int|null $parent_id
 * @property string|null $icon
 * @property string|null $color
 * @property string|null $card_type The type of card the documents of the category go with
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
#[Fillable(['name', 'parent_id', 'icon', 'color', 'sort', 'card_type'])]
final class DocumentCategory extends FamilyModel
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    /**
     * The parent category, if this is a subcategory.
     *
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    /**
     * The subcategories.
     *
     * @return HasMany<DocumentCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DocumentCategory::class, 'parent_id');
    }

    /**
     * The documents of the category, without those of the subcategories.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    /**
     * The card type of a category: its own, else the one of its main category.
     *
     * @param  int|null  $id  the category; one of another family counts as missing (the family scope)
     * @return string|null the type of the morph map, null when the category is missing or has none
     */
    public static function cardTypeOf(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $category = self::query()->find($id, ['id', 'parent_id', 'card_type']);

        if ($category === null) {
            return null;
        }

        if ($category->card_type !== null || $category->parent_id === null) {
            return $category->card_type;
        }

        $type = self::query()->whereKey($category->parent_id)->value('card_type');

        return is_string($type) ? $type : null;
    }

    /**
     * The types of the columns.
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
