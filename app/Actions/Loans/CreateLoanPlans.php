<?php

declare(strict_types=1);

namespace App\Actions\Loans;

use App\Enums\ExceptionType;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Observers\LoanObserver;
use App\Observers\LoanPlanObserver;
use App\Services\LoanSchedule;
use App\Support\DefaultAccount;
use App\Support\LoanTerms;
use Carbon\CarbonImmutable;

/**
 * Creates the planned expense of the instalment of a loan: for a new loan (from {@see LoanObserver}) from its first
 * instalment, and for the loans saved while the Budget was off, when it is turned on, from that day on.
 *
 * A late start is the first instalment on that day or after it, on the same day of the month as the loan. It equals
 * the start of the loan only when the loan starts that day or later; otherwise it differs, so
 * {@see LoanSchedule::expenseChanges()} takes it as written by hand and never brings it back to the past when the loan
 * changes. A loan on the 29th, 30th or 31st whose first instalment from that day falls on a shorter month (28/02) gets
 * that one instalment as a planned expense of its own, and the monthly one from the next instalment on its day: a
 * series always counts from its start, so starting on the 28th would move every later instalment to the 28th.
 */
final readonly class CreateLoanPlans
{
    /**
     * @param  LoanSchedule  $schedule  The rules of the loans.
     * @param  RefreshLoan  $refresh  Works out the figures of a loan again.
     * @param  LoanPlanObserver  $plans  The observer of the planned expenses, kept quiet while this one writes them.
     */
    public function __construct(
        private LoanSchedule $schedule,
        private RefreshLoan $refresh,
        private LoanPlanObserver $plans,
    ) {}

    /**
     * For the current family: creates the planned expense of every loan that has none, with its first due date on
     * `$today` or after it, so no instalment of the past is late.
     *
     * @param  CarbonImmutable  $today  The first day the instalments count.
     * @return int How many loans got their planned expense.
     */
    public function handle(CarbonImmutable $today): int
    {
        $created = 0;

        foreach (Loan::query()->whereDoesntHave('plannedExpenses')->orderBy('id')->get() as $loan) {
            if ($this->plans->muted(fn (): bool => $this->create($loan, $today))) {
                $this->refresh->handle($loan, $today);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Creates the planned expense of the instalment of one loan, with the final balloon payment as its exception.
     * The caller is in the family of the loan.
     *
     * @param  Loan  $loan  The loan.
     * @param  ?CarbonImmutable  $from  The first day the instalments count; null from the first instalment.
     * @return bool Whether it was created: not for a loan paid off, without the instalment or the start, or with
     *              every instalment before `$from`.
     */
    public function create(Loan $loan, ?CarbonImmutable $from = null): bool
    {
        $terms = LoanTerms::of($loan);
        $category = PlannedExpense::query()->where('card_type', (new Loan)->getMorphClass())->whereNotNull('category_id')->latest('id')->value('category_id');
        $columns = $this->schedule->plannedExpense($loan, is_numeric($category) ? (int) $category : null, DefaultAccount::id(), $terms);

        if ($columns === null || ! $terms->start instanceof CarbonImmutable) {
            return false;
        }

        if (! $from instanceof CarbonImmutable) {
            $this->monthly($columns, $terms);

            return true;
        }

        $left = $this->schedule->installmentsFrom($terms, $from);

        if ($left === []) {
            return false;
        }

        // A first instalment moved to the end of a shorter month stands alone; the series starts on the next one
        $alone = $left[0]['date']->day !== $terms->start->day ? array_shift($left) : null;

        if ($left !== []) {
            $this->monthly([...$columns, 'start_date' => $left[0]['date']->toDateString()], $terms);
        }

        if ($alone !== null) {
            PlannedExpense::query()->create([
                ...$columns,
                'amount' => $alone['amount'] ?? $columns['amount'],
                'is_recurring' => false,
                'start_date' => $alone['date']->toDateString(),
                'end_date' => $alone['date']->toDateString(),
            ]);
        }

        return true;
    }

    /**
     * Creates the monthly planned expense, with the final balloon payment as its exception on the last date.
     *
     * @param  array<string, mixed>  $columns  The columns of `planned_expenses`.
     * @param  LoanTerms  $terms  The loan.
     */
    private function monthly(array $columns, LoanTerms $terms): void
    {
        $expense = PlannedExpense::query()->create($columns);
        $balloon = $this->schedule->balloon($terms);

        if ($balloon !== null) {
            $expense->exceptions()->create([...$balloon, 'type' => ExceptionType::AmountOverride]);
        }
    }
}
