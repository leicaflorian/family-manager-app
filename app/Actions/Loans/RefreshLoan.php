<?php

declare(strict_types=1);

namespace App\Actions\Loans;

use App\Enums\ExceptionType;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Services\LoanSchedule;
use App\Support\LoanFigures;
use App\Support\LoanTerms;
use App\Support\Schedule;
use App\Support\ScheduleException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Brings what a loan works out by itself (the last date, the number of instalments, the total, the cost and how far
 * the payments are) in line with its terms, the exceptions of its planned expenses and the day. It works in the
 * current family, which must be the loan's.
 */
final readonly class RefreshLoan
{
    /** The most instalments written on a loan, as many as {@see LoanSchedule} reads. */
    private const int MAX_INSTALLMENTS = 1200;

    /**
     * @param  LoanSchedule  $schedule  The rules of the loans.
     */
    public function __construct(private LoanSchedule $schedule) {}

    /**
     * Works out the figures of a saved loan and writes the ones that changed, without the events of the loan: a
     * refresh does not change what its planned expense follows.
     *
     * @param  Loan  $loan  A loan as it is saved, with nothing else to write.
     * @param  CarbonImmutable  $today  The day the instalments paid are counted on.
     * @return bool Whether something was written.
     */
    public function handle(Loan $loan, CarbonImmutable $today): bool
    {
        if (! $this->fill($loan, $today)) {
            return false;
        }

        $loan->saveQuietly();

        return true;
    }

    /**
     * Puts on the loan the figures that differ from the ones it has, without saving: for the loan being saved.
     *
     * For a loan being saved, `$before` is the loan before the save: its planned expense will move or change the
     * exception of the final balloon payment after the save ({@see LoanSchedule::balloonChanges()}), so the figures
     * are worked out with that exception as it will be, and the loan is written once.
     *
     * @param  Loan  $loan  The loan.
     * @param  CarbonImmutable  $today  The day the instalments paid are counted on.
     * @param  ?array<string, mixed>  $before  The columns of the loan before the save, when it is being saved.
     * @return bool Whether a figure changed.
     */
    public function fill(Loan $loan, CarbonImmutable $today, ?array $before = null): bool
    {
        $changed = false;
        $terms = LoanTerms::of($loan);
        $exceptions = $this->exceptions($loan, $before === null ? null : LoanTerms::fromForm($before), $terms);

        foreach ($this->columns($loan, $this->schedule->derive($terms, $exceptions, $today)) as $column => $value) {
            if ($this->same($loan->getAttribute($column), $value)) {
                continue;
            }

            $loan->setAttribute($column, $value);
            $changed = true;
        }

        return $changed;
    }

    /**
     * The figures a loan would have with the terms of a form, worked out as saving them would: with the saved
     * exceptions of its planned expenses and the final balloon payment as it would follow the new terms. Nothing
     * is written.
     *
     * @param  ?Loan  $saved  The loan being changed, or null for a new one (no exceptions yet).
     * @param  LoanTerms  $terms  The terms written in the form.
     * @param  CarbonImmutable  $today  The day the instalments paid are counted on.
     */
    public function preview(?Loan $saved, LoanTerms $terms, CarbonImmutable $today): LoanFigures
    {
        $exceptions = $saved instanceof Loan ? $this->exceptions($saved, LoanTerms::of($saved), $terms) : [];

        return $this->schedule->derive($terms, $exceptions, $today);
    }

    /**
     * The planned expense that follows the loan: the first one created for it. One linked by hand never follows.
     *
     * @param  Collection<int, PlannedExpense>  $expenses  The planned expenses linked to the loan.
     */
    public function followed(Collection $expenses): ?PlannedExpense
    {
        return $expenses->sortBy('id')->first(fn (PlannedExpense $expense): bool => $expense->is_auto_created);
    }

    /**
     * The columns of the loan for its figures.
     *
     * @param  Loan  $loan  The loan.
     * @param  LoanFigures  $figures  Its figures.
     * @return array<string, int|float|string|null>
     */
    private function columns(Loan $loan, LoanFigures $figures): array
    {
        // A count worked out from far apart dates can be past what the rules read: the loan keeps the one it has.
        $count = $figures->installmentsCount !== null && $figures->installmentsCount > self::MAX_INSTALLMENTS
            ? $loan->installments_count
            : $figures->installmentsCount;

        return [
            'end_date' => $figures->endDate?->toDateString(),
            'installments_count' => $count,
            'total_to_pay' => $figures->totalToPay,
            'cost' => $figures->cost,
            'installments_paid' => $figures->installmentsPaid,
            'installments_left' => $figures->installmentsLeft,
            'residual_amount' => $figures->residual,
        ];
    }

    /**
     * The exceptions of the planned expenses linked to the loan.
     *
     * @param  Loan  $loan  The loan.
     * @param  ?LoanTerms  $before  The terms of the loan before the save, when it is being saved.
     * @param  LoanTerms  $after  The terms the loan is saved with.
     * @return list<ScheduleException> None for a loan not saved yet.
     */
    private function exceptions(Loan $loan, ?LoanTerms $before, LoanTerms $after): array
    {
        if (! $loan->exists) {
            return [];
        }

        $expenses = $loan->plannedExpenses()->with('exceptions')->get();
        $followed = $before instanceof LoanTerms ? $this->followed($expenses) : null;

        if ($followed instanceof PlannedExpense) {
            $followed->setRelation('exceptions', $this->afterBalloon($before, $after, $followed->exceptions));
        }

        $exceptions = [];

        foreach ($expenses as $planned) {
            array_push($exceptions, ...Schedule::of($planned)->exceptions);
        }

        return $exceptions;
    }

    /**
     * The exceptions of the planned expense as they will be once the final balloon payment follows the loan, in
     * memory only: the loan observer writes them after the save.
     *
     * @param  LoanTerms  $before  The terms of the loan before the save.
     * @param  LoanTerms  $after  The terms the loan is saved with.
     * @param  Collection<int, PlannedExpenseException>  $exceptions  The saved exceptions of the planned expense.
     * @return Collection<int, PlannedExpenseException>
     */
    private function afterBalloon(LoanTerms $before, LoanTerms $after, Collection $exceptions): Collection
    {
        $changes = $this->schedule->balloonChanges($before, $after, $exceptions);

        if (isset($changes['update'])) {
            [$id, $values] = $changes['update'];
            $exceptions = $exceptions->map(fn (PlannedExpenseException $e): PlannedExpenseException => $e->id === $id ? (clone $e)->fill($values) : $e);
        }

        if (isset($changes['remove'])) {
            $removed = $changes['remove'];
            $exceptions = $exceptions->reject(fn (PlannedExpenseException $e): bool => $e->id === $removed);
        }

        if (isset($changes['create'])) {
            $exceptions = $exceptions->push(new PlannedExpenseException([...$changes['create'], 'type' => ExceptionType::AmountOverride]));
        }

        return $exceptions->values();
    }

    /**
     * Whether the saved value is the worked out one: dates by their day, numbers by value.
     *
     * @param  mixed  $saved  The value on the loan.
     * @param  int|float|string|null  $value  The worked out value.
     */
    private function same(mixed $saved, int|float|string|null $value): bool
    {
        if ($saved === null || $value === null) {
            return $saved === null && $value === null;
        }

        if ($saved instanceof DateTimeInterface) {
            return $saved->format('Y-m-d') === $value;
        }

        return is_numeric($saved) && is_numeric($value) && (float) $saved === (float) $value;
    }
}
