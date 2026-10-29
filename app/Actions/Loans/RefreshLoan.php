<?php

declare(strict_types=1);

namespace App\Actions\Loans;

use App\Models\Loan;
use App\Services\LoanSchedule;
use App\Support\LoanFigures;
use App\Support\LoanTerms;
use App\Support\Schedule;
use App\Support\ScheduleException;
use Carbon\CarbonImmutable;
use DateTimeInterface;

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
     * @param  Loan  $loan  The loan.
     * @param  CarbonImmutable  $today  The day the instalments paid are counted on.
     * @return bool Whether a figure changed.
     */
    public function fill(Loan $loan, CarbonImmutable $today): bool
    {
        $changed = false;

        foreach ($this->columns($loan, $this->schedule->derive(LoanTerms::of($loan), $this->exceptions($loan), $today)) as $column => $value) {
            if ($this->same($loan->getAttribute($column), $value)) {
                continue;
            }

            $loan->setAttribute($column, $value);
            $changed = true;
        }

        return $changed;
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
     * @return list<ScheduleException> None for a loan not saved yet.
     */
    private function exceptions(Loan $loan): array
    {
        if (! $loan->exists) {
            return [];
        }

        $exceptions = [];

        foreach ($loan->plannedExpenses()->with('exceptions')->get() as $planned) {
            array_push($exceptions, ...Schedule::of($planned)->exceptions);
        }

        return $exceptions;
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
