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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a due date to the payments that have none: for each expense or income
 * of a planned item, the occurrence closest to the date of the payment. It is for
 * the data copied from Directus, which did not save which due date a payment settles.
 * With {@see remap()} it also moves the payments and the exceptions of a planned item
 * whose schedule has changed onto its new due dates, so the months paid stay paid.
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
     * Moves the payments and the exceptions of one planned item onto the due dates of its current schedule: each one
     * goes to the due date closest to the one it named (a payment without one, to the closest to its day), within its
     * window. A payment or an exception left without a due date keeps its old one.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item, already saved with its new schedule.
     * @return int How many rows were written.
     */
    public function remap(PlannedExpense|PlannedIncome $planned): int
    {
        $planned->load('exceptions');
        $schedule = Schedule::of($planned);
        // The old due dates are matched against the bare series: an exception still keyed on an old date must not hide the new one
        $series = new Schedule($schedule->start, $schedule->end, $schedule->recurring, $schedule->interval, $schedule->unit, $schedule->amount, $schedule->estimated);
        $payments = $planned instanceof PlannedExpense ? $planned->expenses()->get() : $planned->incomes()->get();
        $written = 0;

        foreach ($payments as $payment) {
            $named = $payment->occurrence_date;
            $occurrence = $named === null
                ? ($payment->date === null ? null : $this->occurrences->at($schedule, $payment->date))
                : $this->occurrences->at($series, $named);
            $written += (int) $this->write($payment, $occurrence);
        }

        foreach ($planned->exceptions as $exception) {
            $named = $exception->occurrence_date;
            $written += (int) ($named !== null && $this->write($exception, $this->occurrences->at($series, $named)));
        }

        $planned->unsetRelation('exceptions');

        return $written;
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

            $written += (int) $this->write($payment, $this->occurrences->at(Schedule::of($item), $date));
        }

        return $written;
    }

    /**
     * Writes the original date of an occurrence as the due date a row names, when there is one and it is another day.
     *
     * @param  Model  $row  An expense, an income or an exception, with `occurrence_date`.
     * @param  ?Occurrence  $occurrence  The occurrence it now belongs to, null when there is none.
     * @return bool Whether the row was written.
     */
    private function write(Model $row, ?Occurrence $occurrence): bool
    {
        $current = $row->getAttribute('occurrence_date');

        if (! $occurrence instanceof Occurrence || ($current instanceof CarbonImmutable && $current->toDateString() === $occurrence->originalDate->toDateString())) {
            return false;
        }

        $row->setAttribute('occurrence_date', $occurrence->originalDate);
        // Filling or moving the due date is not an edit: the row keeps its dates, and no observer runs again.
        Model::withoutTimestamps(static fn (): bool => $row->saveQuietly());

        return true;
    }
}
