<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Finds a planned expense or income from the two parts of its address (`expense` or `income`, and the id),
 * always inside the current family.
 */
final class PlannedKind
{
    /**
     * The planned item of an address.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  string  $id  the id of the planned item
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     * @throws ModelNotFoundException if no planned item of the family has that id (Laravel answers 404)
     */
    public static function find(string $kind, string $id): PlannedExpense|PlannedIncome
    {
        return match ($kind) {
            'expense' => PlannedExpense::query()->findOrFail($id),
            'income' => PlannedIncome::query()->findOrFail($id),
            default => throw new NotFoundHttpException,
        };
    }
}
