<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una categoria delle spese, con le sue sottocategorie (`expense_categories`).
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
 * @property-read Collection<int, ExpenseCategory> $children
 * @property-read int|null $children_count
 * @property-read \App\Models\Family $family
 * @property-read ExpenseCategory|null $parent
 * @method static \Database\Factories\ExpenseCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ExpenseCategory newModelQuery()
 * @method static Builder<static>|ExpenseCategory newQuery()
 * @method static Builder<static>|ExpenseCategory query()
 * @mixin \Eloquent
 */
#[Fillable(['name', 'parent_id', 'icon', 'color', 'sort'])]
final class ExpenseCategory extends DatoDellaFamiglia
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory;

    /**
     * La categoria madre, se questa è una sottocategoria.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * Le sottocategorie.
     *
     * @return HasMany<ExpenseCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
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
