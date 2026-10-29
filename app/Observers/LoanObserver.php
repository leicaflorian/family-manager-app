<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Loans\RefreshLoan;
use App\Enums\ExceptionType;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Services\LoanSchedule;
use App\Support\CurrentFamily;
use App\Support\DefaultAccount;
use App\Support\LoanTerms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * What a loan does by itself when it is saved: it works out its figures in the same save, then creates the planned
 * expense of its instalment, or brings the one created for it in line with the loan.
 */
final readonly class LoanObserver
{
    /** The columns of the loan its planned expense depends on: a save that changes none leaves it alone. */
    private const array FOLLOWED = [
        'name', 'status', 'installment_amount', 'final_installment_amount', 'start_date', 'end_date',
        'organization_id', 'financial_account_id', 'person_id',
    ];

    /** The columns of the figures, which a refresh after the planned expense may change. */
    private const array FIGURES = [
        'end_date', 'installments_count', 'total_to_pay', 'cost', 'installments_paid', 'installments_left', 'residual_amount',
    ];

    /**
     * @param  RefreshLoan  $refresh  Works out the figures.
     * @param  LoanSchedule  $schedule  The rules of the loans.
     * @param  LoanPlanObserver  $plans  The observer of the planned expenses, kept quiet while this one writes them.
     */
    public function __construct(
        private RefreshLoan $refresh,
        private LoanSchedule $schedule,
        private LoanPlanObserver $plans,
    ) {}

    /**
     * Puts the figures in the save, with the exceptions of the planned expenses linked to the loan.
     *
     * @param  Loan  $loan  The loan being saved.
     */
    public function saving(Loan $loan): void
    {
        $fill = fn (): bool => $this->refresh->fill($loan, CarbonImmutable::today());

        // A new loan has no family yet (it comes in `creating`) and no planned expenses to read.
        $loan->exists ? CurrentFamily::using($loan->family_id, $fill) : $fill();
    }

    /**
     * Creates the planned expense of the instalment when the loan has none, or makes the one created for it follow
     * the loan. The loan before the save is still in `getOriginal()`.
     *
     * @param  Loan  $loan  The saved loan.
     */
    public function saved(Loan $loan): void
    {
        $created = $loan->getRawOriginal('id') === null;

        if (! $created && ! $loan->wasChanged(self::FOLLOWED)) {
            return;
        }

        $before = $created ? [] : $loan->getOriginal();

        CurrentFamily::using($loan->family_id, function () use ($loan, $before): void {
            $expenses = $loan->plannedExpenses()->with('exceptions')->orderBy('id')->get();
            $today = CarbonImmutable::today();

            $written = $this->plans->muted(fn (): bool => $expenses->isEmpty()
                ? $this->create($loan)
                : $this->follow($loan, $before, $expenses->first(fn (PlannedExpense $expense): bool => $expense->is_auto_created), $today));

            if ($written) {
                $this->refreshAfter($loan, $today);
            }
        });
    }

    /**
     * Creates the planned expense of the instalment, with the final balloon payment as its exception.
     *
     * @param  Loan  $loan  The loan.
     * @return bool Whether it was created: not for a loan paid off or without the instalment or the start.
     */
    private function create(Loan $loan): bool
    {
        $category = PlannedExpense::query()->whereNotNull('loan_id')->whereNotNull('category_id')->latest('id')->value('category_id');
        $columns = $this->schedule->plannedExpense($loan, is_numeric($category) ? (int) $category : null, DefaultAccount::id());

        if ($columns === null) {
            return false;
        }

        $expense = PlannedExpense::query()->create($columns);
        $balloon = $this->schedule->balloon(LoanTerms::of($loan));

        if ($balloon !== null) {
            $expense->exceptions()->create([...$balloon, 'type' => ExceptionType::AmountOverride]);
        }

        return true;
    }

    /**
     * Brings the planned expense created for the loan in line with the change of the loan.
     *
     * @param  Loan  $loan  The saved loan.
     * @param  array<string, mixed>  $before  The columns of the loan before the save.
     * @param  ?PlannedExpense  $expense  The planned expense created for the loan, if there is one.
     * @param  CarbonImmutable  $today  The day a paid off loan ends the planned expense.
     * @return bool Whether something was written.
     */
    private function follow(Loan $loan, array $before, ?PlannedExpense $expense, CarbonImmutable $today): bool
    {
        if (! $expense instanceof PlannedExpense) {
            return false;
        }

        $patch = $this->schedule->expenseChanges($before, $loan->only(array_keys($loan->getAttributes())), $expense, $today, DefaultAccount::id());

        if ($patch !== []) {
            $expense->update($patch);
        }

        $balloon = $this->schedule->balloonChanges(LoanTerms::fromForm($before), LoanTerms::of($loan), $expense->exceptions);

        if (isset($balloon['update'])) {
            PlannedExpenseException::query()->whereKey($balloon['update'][0])->firstOrFail()->update($balloon['update'][1]);
        }

        if (isset($balloon['remove'])) {
            PlannedExpenseException::query()->whereKey($balloon['remove'])->firstOrFail()->delete();
        }

        if (isset($balloon['create'])) {
            $expense->exceptions()->create([...$balloon['create'], 'type' => ExceptionType::AmountOverride]);
        }

        return $patch !== [] || $balloon !== [];
    }

    /**
     * Works out the figures again after the planned expense changed, once, on a copy of the loan: the loan itself
     * is still in the middle of its save, and saving it again would write its changes twice.
     *
     * @param  Loan  $loan  The saved loan, which gets the new figures too.
     * @param  CarbonImmutable  $today  The day the instalments paid are counted on.
     */
    private function refreshAfter(Loan $loan, CarbonImmutable $today): void
    {
        $copy = Loan::query()->whereKey($loan->id)->firstOrFail();

        if ($this->refresh->handle($copy, $today)) {
            $loan->setRawAttributes([...$loan->getAttributes(), ...Arr::only($copy->getAttributes(), self::FIGURES)]);
        }
    }
}
