<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Occurrences;
use App\Support\Occurrence;
use App\Support\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a due date to the payments that have none: for each expense or income
 * of a planned item, the occurrence closest to the date of the payment. It is for
 * the data copied from Directus, which did not save which due date a payment settles.
 * It works in the current family only.
 */
final readonly class FillOccurrenceDates
{
    public function __construct(private Occurrences $occurrences) {}

    /**
     * Writes `occurrence_date` on the expenses and incomes of the current family that have a planned item and no date yet.
     *
     * @return int How many rows were written.
     */
    public function handle(): int
    {
        $expenses = Expense::query()->whereNotNull('planned_expense_id')->whereNull('occurrence_date')->get();
        $incomes = Income::query()->whereNotNull('planned_income_id')->whereNull('occurrence_date')->get();

        $plannedExpenses = PlannedExpense::query()->with('exceptions')->whereKey($expenses->pluck('planned_expense_id')->unique()->all())->get();
        $plannedIncomes = PlannedIncome::query()->with('exceptions')->whereKey($incomes->pluck('planned_income_id')->unique()->all())->get();

        return $this->fill($expenses, 'planned_expense_id', $this->byId($plannedExpenses))
            + $this->fill($incomes, 'planned_income_id', $this->byId($plannedIncomes));
    }

    /**
     * The planned items by id.
     *
     * @param  Collection<int, PlannedExpense>|Collection<int, PlannedIncome>  $planned  The planned items.
     * @return array<int, PlannedExpense|PlannedIncome>
     */
    private function byId(Collection $planned): array
    {
        $found = [];

        foreach ($planned as $item) {
            $found[$item->id] = $item;
        }

        return $found;
    }

    /**
     * Writes the date on each payment whose planned item has an occurrence for it.
     *
     * @param  Collection<int, Expense>|Collection<int, Income>  $payments  The payments without a date.
     * @param  string  $column  The column of the payments that holds the planned item's id.
     * @param  array<int, PlannedExpense|PlannedIncome>  $planned  The planned items, by id.
     * @return int How many payments got a date.
     */
    private function fill(Collection $payments, string $column, array $planned): int
    {
        $written = 0;

        foreach ($payments as $payment) {
            $id = $payment->getAttribute($column);
            $date = $payment->date;
            $item = is_int($id) ? ($planned[$id] ?? null) : null;

            if ($item === null || $date === null) {
                continue;
            }

            $occurrence = $this->occurrences->at(Schedule::of($item), $date);

            if (! $occurrence instanceof Occurrence) {
                continue;
            }

            $payment->occurrence_date = $occurrence->originalDate;
            // The copy keeps the dates of the original rows: filling the due date is not an edit.
            Model::withoutTimestamps(static fn (): bool => $payment->saveQuietly());
            $written++;
        }

        return $written;
    }
}
