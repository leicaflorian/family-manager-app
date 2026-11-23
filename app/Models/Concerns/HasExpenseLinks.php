<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * For an expense and a planned expense: the links of {@see HasMovementLinks}, the category and the card it refers to.
 */
trait HasExpenseLinks
{
    use HasMovementLinks;

    /**
     * The category.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * The card it refers to (a utility, a vehicle for a parking, a home, a loan…), if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function card(): MorphTo
    {
        return $this->morphTo();
    }
}
