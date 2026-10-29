<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ExpenseCategoryPaths;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expense category, with its subcategories (`expense_categories`).
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
 * @property string|null $card_type The type of card the expenses of the category refer to
 * @property-read Collection<int, ExpenseCategory> $children
 * @property-read int|null $children_count
 * @property-read Family $family
 * @property-read ExpenseCategory|null $parent
 *
 * @method static ExpenseCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ExpenseCategory newModelQuery()
 * @method static Builder<static>|ExpenseCategory newQuery()
 * @method static Builder<static>|ExpenseCategory query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'parent_id', 'icon', 'color', 'sort', 'card_type'])]
final class ExpenseCategory extends FamilyModel
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory;

    /**
     * The parent category, if this is a subcategory.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * The subcategories.
     *
     * @return HasMany<ExpenseCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }

    /**
     * The card type of every category of the current family, with the inheritance from the main category, in one
     * query. A category without a type is left out.
     *
     * @return array<int, string> the types of the morph map, by id of the category
     */
    public static function cardTypes(): array
    {
        $categories = self::query()->get(['id', 'parent_id', 'name', 'card_type']);
        $byId = $categories->keyBy('id');
        $types = [];

        foreach (ExpenseCategoryPaths::walk($categories) as $id => ['main' => $main]) {
            $type = $byId->get($main)?->card_type;

            if ($type !== null) {
                $types[$id] = $type;
            }
        }

        return $types;
    }

    /**
     * The first main category (by order, then name) whose expenses refer to cards of a type: the one a new expense or
     * planned expense that comes from a card starts with.
     *
     * @param  string  $type  the type of the card, as in the morph map
     * @return int|null its id, null when no main category has that type
     */
    public static function firstOfCardType(string $type): ?int
    {
        $id = self::query()->whereNull('parent_id')->where('card_type', $type)->orderBy('sort')->orderBy('name')->value('id');

        return is_int($id) ? $id : null;
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
