<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Actions\Budget\SaveMovement;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterOutcome;
use App\Enums\BankMatchSource;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankDuplicates;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\BankText;
use App\Support\Money;
use App\Support\PaymentTitle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Does with a bank transaction what its filter says: pays the due date of a planned item, creates the expense (or
 * income), marks a transfer or puts it aside, and writes on the transaction which filter did it. Before paying or
 * creating, an expense somebody already recorded for it is linked instead (never a second one). Runs in a request and
 * in the queued read of a bank, so it never needs a signed-in account.
 */
final readonly class ApplyBankFilter
{
    public function __construct(
        private BankDuplicates $duplicates,
        private SettleFromBank $settle,
        private SaveMovement $saveMovement,
        private AttachBankFee $attachFee,
        private MarkBankTransfer $transfers,
    ) {}

    /**
     * Applies the filter to the transaction, in one transaction. The bank row is locked first and must still be to
     * sort and not declined (*It was not this one*); then the filter is read again with a locking read (it may have
     * changed or lost its category, organization or account since it was read), then the planned item of a filter
     * that pays is locked: no plain read comes before these locks, so two calls on the same transaction at once settle
     * it once, the second finds it no longer to sort. A commission is never taken by a filter: it joins the expense of
     * its debit by itself (`AttachBankFee`).
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  BankFilter  $filter  The filter that takes it.
     * @return BankFilterOutcome what the filter did; `None` when the transaction stays to sort (or is not to sort any more)
     */
    public function handle(BankTransaction $transaction, BankFilter $filter): BankFilterOutcome
    {
        return DB::transaction(function () use ($transaction, $filter): BankFilterOutcome {
            $current = BankTransaction::query()->unsorted()->whereNull('declined_at')->whereKey($transaction->id)->lockForUpdate()->first();

            if (! $current instanceof BankTransaction || ! $current->booked_on instanceof CarbonImmutable || BankText::isFee($current->text)) {
                return BankFilterOutcome::None;
            }

            // A shared lock: two transactions taken by the same filter do not wait for each other, and it fixes no snapshot
            $fresh = BankFilter::query()->whereKey($filter->id)->sharedLock()->first();

            if (! $fresh instanceof BankFilter || ! BankFilterMatch::matches($fresh->criteria(), $current)) {
                return BankFilterOutcome::None;
            }

            $outcome = match ($fresh->action) {
                BankFilterAction::Pay => $this->pay($current, $fresh),
                BankFilterAction::Create => $this->create($current, $fresh),
                BankFilterAction::Transfer => $this->transfer($current, $fresh),
                BankFilterAction::Ignore => $this->ignore($current),
            };

            if ($outcome !== BankFilterOutcome::None) {
                $current->forceFill(['bank_filter_id' => $fresh->id])->save();
            }

            return $outcome;
        });
    }

    /**
     * Pays the due date of the planned item of the filter as a rule did (`SettleFromBank`, origin `Rule`): a payment of
     * it already recorded is linked, otherwise the due date the transaction settles is paid. Nothing when the planned
     * item is gone, is of the other direction or ended before the day, or has no due date to pay.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  BankFilter  $filter  The filter, read again.
     */
    private function pay(BankTransaction $transaction, BankFilter $filter): BankFilterOutcome
    {
        $planned = $this->lockPlanned($filter);
        $day = $transaction->booked_on?->toDateString();

        if ($planned === null || ($planned instanceof PlannedExpense) !== ($transaction->kind() === 'expense')
            || ($planned->end_date !== null && $planned->end_date->toDateString() < (string) $day)) {
            return BankFilterOutcome::None;
        }

        $payment = $this->settle->handle($transaction, $planned, BankMatchSource::Rule, null, $filter);

        if ($payment === null) {
            return BankFilterOutcome::None;
        }

        return $transaction->refresh()->matched_by === BankMatchSource::Rule ? BankFilterOutcome::Paid : BankFilterOutcome::Linked;
    }

    /**
     * Creates the expense (or income) with the fields of the filter and the amount, day and account of the transaction,
     * with the month in the title when the filter asks, and links it (origin `Rule`), with the commission found as the
     * filter says. An expense already recorded for the transaction is linked instead (`Duplicate`).
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  BankFilter  $filter  The filter, read again.
     */
    private function create(BankTransaction $transaction, BankFilter $filter): BankFilterOutcome
    {
        $recorded = $this->duplicates->findAny($transaction, null, $filter);

        if ($recorded !== null && $this->duplicates->claim($recorded, $transaction)) {
            $this->link($transaction, $recorded, BankMatchSource::Duplicate, $filter);

            return BankFilterOutcome::Linked;
        }

        $kind = $transaction->kind();
        $this->link($transaction, $this->saveMovement->handle($kind, $this->columns($transaction, $filter, $kind)), BankMatchSource::Rule, $filter);

        return BankFilterOutcome::Created;
    }

    /**
     * The columns of the movement a filter creates. A card that no longer exists is left out; a category, an
     * organization or a person that was deleted is already empty on the filter read again.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  BankFilter  $filter  The filter, read again.
     * @param  string  $kind  `expense` or `income`
     * @return array<string, mixed>
     */
    private function columns(BankTransaction $transaction, BankFilter $filter, string $kind): array
    {
        $title = $filter->title !== null && trim($filter->title) !== '' ? $filter->title : $filter->name;
        $day = $transaction->booked_on ?? CarbonImmutable::now('Europe/Rome');
        $columns = [
            'title' => $filter->title_with_month ? PaymentTitle::for($title, $day) : $title,
            'amount' => Money::decimal(abs(Money::cents($transaction->amount))),
            'date' => $day->toDateString(),
            'financial_account_id' => $transaction->financial_account_id,
            'organization_id' => $filter->organization_id,
            'person_id' => $filter->person_id,
            'notes' => $filter->notes,
        ];

        if ($kind === 'income') {
            return $columns;
        }

        $card = $filter->card_type !== null && $filter->card_id !== null ? $filter->card : null;

        return [
            ...$columns,
            'category_id' => $filter->category_id,
            'card_type' => $card instanceof Model ? $filter->card_type : null,
            'card_id' => $card instanceof Model ? $filter->card_id : null,
        ];
    }

    /**
     * Marks the transaction as a transfer towards the account of the filter. Nothing when the account is gone or is
     * the account of the transaction itself.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  BankFilter  $filter  The filter, read again.
     */
    private function transfer(BankTransaction $transaction, BankFilter $filter): BankFilterOutcome
    {
        $other = $filter->transfer_account_id === null ? null : FinancialAccount::query()->find($filter->transfer_account_id);

        if (! $other instanceof FinancialAccount) {
            return BankFilterOutcome::None;
        }

        try {
            $this->transfers->handle($transaction, $other);
        } catch (ValidationException) {
            return BankFilterOutcome::None;
        }

        return BankFilterOutcome::Transferred;
    }

    /**
     * Puts the transaction aside.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     */
    private function ignore(BankTransaction $transaction): BankFilterOutcome
    {
        $transaction->forceFill(['ignored_at' => now()])->save();

        return BankFilterOutcome::Ignored;
    }

    /**
     * Finds and locks the planned item of a filter that pays, in one locking read, right after the bank row.
     *
     * @param  BankFilter  $filter  The filter.
     */
    private function lockPlanned(BankFilter $filter): PlannedExpense|PlannedIncome|null
    {
        return match (true) {
            $filter->planned_expense_id !== null => PlannedExpense::query()->whereKey($filter->planned_expense_id)->lockForUpdate()->first(),
            $filter->planned_income_id !== null => PlannedIncome::query()->whereKey($filter->planned_income_id)->lockForUpdate()->first(),
            default => null,
        };
    }

    /**
     * Links the transaction to a movement, and the commission of a direct debit to the same expense, found as the
     * filter says.
     *
     * @param  BankTransaction  $transaction  The transaction, locked.
     * @param  Expense|Income  $movement  The movement.
     * @param  BankMatchSource  $by  How the link came.
     * @param  BankFilter  $filter  The filter.
     */
    private function link(BankTransaction $transaction, Expense|Income $movement, BankMatchSource $by, BankFilter $filter): void
    {
        $transaction->linkTo($movement, $by);
        $this->attachFee->forDebit($transaction, $filter);
    }
}
