<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Locks the row of a planned item until the transaction ends, so that two payments of the same due date
     * wait for each other and the second one sees the first. It must be the first query of the transaction:
     * MySQL takes the snapshot of a transaction at its first plain read, and a read before the lock would not see
     * the payment the other transaction has just committed.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    public static function lock(PlannedExpense|PlannedIncome $planned): void
    {
        $planned->newQuery()->whereKey($planned->getKey())->lockForUpdate()->first();
    }

    /**
     * The card a planned item refers to: the first of the cards of a planned expense, the employment of an income.
     * The relations are read as loaded; `Deadlines::between()` loads them for a whole collection.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    public static function card(PlannedExpense|PlannedIncome $planned): ?Model
    {
        return $planned instanceof PlannedExpense
            ? ($planned->utility ?? $planned->insurancePolicy ?? $planned->loan ?? $planned->enrollment ?? $planned->home ?? $planned->vehicle ?? $planned->subscription)
            : $planned->employment;
    }

    /**
     * The model class of a kind.
     *
     * @param  string  $kind  `expense` or `income`
     * @return class-string<PlannedExpense|PlannedIncome>
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     */
    public static function modelClass(string $kind): string
    {
        return match ($kind) {
            'expense' => PlannedExpense::class,
            'income' => PlannedIncome::class,
            default => throw new NotFoundHttpException,
        };
    }
}
