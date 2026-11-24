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
 * Creates the planned expense of the instalment of a loan: for a new loan (from {@see LoanObserver})
 * from its first instalment, and for the loans saved while the Budget was off, when it is turned on, from that day
 * on. The start of a late one differs from the start of the loan, so {@see LoanSchedule::expenseChanges()} takes it
 * as written by hand and never brings it back to the past when the loan changes.
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
     * @return int How many planned expenses it created.
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
        $category = PlannedExpense::query()->where('card_type', (new Loan)->getMorphClass())->whereNotNull('category_id')->latest('id')->value('category_id');
        $columns = $this->schedule->plannedExpense($loan, is_numeric($category) ? (int) $category : null, DefaultAccount::id());
        $terms = LoanTerms::of($loan);

        if ($columns === null) {
            return false;
        }

        if ($from instanceof CarbonImmutable) {
            $first = $this->schedule->firstInstallmentFrom($terms, $from);

            if (! $first instanceof CarbonImmutable) {
                return false;
            }

            $columns['start_date'] = $first->toDateString();
        }

        $expense = PlannedExpense::query()->create($columns);
        $balloon = $this->schedule->balloon($terms);

        if ($balloon !== null) {
            $expense->exceptions()->create([...$balloon, 'type' => ExceptionType::AmountOverride]);
        }

        return true;
    }
}
