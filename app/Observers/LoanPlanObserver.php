<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Loans\RefreshLoan;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

/**
 * Works out the loan again when a planned expense linked to it, or an exception of one, is saved or removed: a
 * moved, skipped or different instalment changes the total and how far the payments are.
 *
 * It is one instance per request (scoped in `AppServiceProvider`), so {@see LoanObserver} can write the planned
 * expense of a loan without coming back here, and refreshes the loan once by itself.
 */
final class LoanPlanObserver
{
    /** Whether the loan observer is writing the planned expense, and the loan needs no refresh from here. */
    private bool $muted = false;

    /**
     * @param  RefreshLoan  $refresh  Works out the loan.
     */
    public function __construct(private readonly RefreshLoan $refresh) {}

    /**
     * Runs some writes of planned expenses and exceptions without refreshing their loans.
     *
     * @template T
     *
     * @param  callable(): T  $work  The writes.
     * @return T Whatever the work returns.
     */
    public function muted(callable $work): mixed
    {
        $previous = $this->muted;
        $this->muted = true;

        try {
            return $work();
        } finally {
            $this->muted = $previous;
        }
    }

    /**
     * A planned expense or an exception was created or changed.
     *
     * @param  PlannedExpense|PlannedExpenseException  $model  What was saved.
     */
    public function saved(PlannedExpense|PlannedExpenseException $model): void
    {
        $this->refreshLoansOf($model);
    }

    /**
     * A planned expense or an exception was removed.
     *
     * @param  PlannedExpense|PlannedExpenseException  $model  What was removed.
     */
    public function deleted(PlannedExpense|PlannedExpenseException $model): void
    {
        $this->refreshLoansOf($model);
    }

    /**
     * Refreshes the loans the planned expense belongs and belonged to, in the family of the row.
     *
     * @param  PlannedExpense|PlannedExpenseException  $model  The planned expense or the exception.
     */
    private function refreshLoansOf(PlannedExpense|PlannedExpenseException $model): void
    {
        if ($this->muted) {
            return;
        }

        CurrentFamily::using($model->family_id, function () use ($model): void {
            $loanIds = $model instanceof PlannedExpense
                ? [$model->loan_id, $model->getOriginal('loan_id')]
                : PlannedExpense::query()
                    ->whereKey([$model->planned_expense_id, $model->getOriginal('planned_expense_id')])
                    ->pluck('loan_id')
                    ->all();

            $loanIds = array_values(array_unique(array_filter($loanIds, is_int(...))));

            if ($loanIds === []) {
                return;
            }

            $today = CarbonImmutable::today();

            foreach (Loan::query()->whereKey($loanIds)->get() as $loan) {
                $this->refresh->handle($loan, $today);
            }
        });
    }
}
