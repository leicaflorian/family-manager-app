<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Actions\Budget\SaveMovement;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterOutcome;
use App\Enums\BankMatchSource;
use App\Enums\FinancialAccountStatus;
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
use App\Support\Deadline;
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
     * What `handle()` would do with the transaction, without locks and without writing anything: the preview of a
     * filter, saved or not, asks it for each transaction to sort that the filter takes, oldest first as the save
     * applies them, passing what the earlier ones would take (`$used`). The same checks and the same steps as
     * `handle()`: a filter that pays finds the payment to link or the due date to pay as `SettleFromBank` would; one
     * that creates links an expense already recorded and free, else creates; a transfer needs its account, open and
     * another than the one of the transaction. The planned item and the account of the transfer are read through the
     * relations of the filter, so once per filter instance. Whether the transaction is still to sort, and whether
     * another filter takes it too, is for the caller to ask.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  BankFilter  $filter  The filter, saved or not.
     * @param  list<string>  $used  what earlier transactions would take: `expense:<id>`, `income:<id>` of a payment linked, `due:<yyyy-mm-dd>` of a due date paid
     * @return array{outcome: BankFilterOutcome, title: ?string, on: ?string, used: ?string} what it would do, the title of the movement it would pay, create or link (or the account of the transfer), the day of the payment it would link or of the due date it would pay (`yyyy-mm-dd`), and what it takes for the next ones (as in `$used`)
     */
    public function foresee(BankTransaction $transaction, BankFilter $filter, array $used = []): array
    {
        if (! $transaction->booked_on instanceof CarbonImmutable || BankText::isFee($transaction->text)) {
            return $this->nothing();
        }

        return match ($filter->action) {
            BankFilterAction::Pay => $this->foreseePay($transaction, $filter, $used),
            BankFilterAction::Create => $this->foreseeCreate($transaction, $filter, $used),
            BankFilterAction::Transfer => ($other = $this->transferAccount($transaction, $filter)) instanceof FinancialAccount
                ? ['outcome' => BankFilterOutcome::Transferred, 'title' => $other->name, 'on' => null, 'used' => null]
                : $this->nothing(),
            BankFilterAction::Ignore => ['outcome' => BankFilterOutcome::Ignored, 'title' => null, 'on' => null, 'used' => null],
        };
    }

    /**
     * What a filter that pays would do: link a payment already recorded, pay a due date, or nothing.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  BankFilter  $filter  The filter.
     * @param  list<string>  $used  what earlier transactions would take
     * @return array{outcome: BankFilterOutcome, title: ?string, on: ?string, used: ?string}
     */
    private function foreseePay(BankTransaction $transaction, BankFilter $filter, array $used): array
    {
        $planned = $filter->plannedExpense ?? $filter->plannedIncome;
        $found = $planned !== null && $this->payable($planned, $transaction) ? $this->settle->foresee($transaction, $planned, $filter, $used) : null;

        return match (true) {
            $found instanceof Deadline => [
                'outcome' => BankFilterOutcome::Paid, 'title' => $found->title, 'on' => $found->occurrence->date->toDateString(),
                'used' => BankDuplicates::dueKey($found->occurrence->originalDate),
            ],
            $found instanceof Expense, $found instanceof Income => $this->linked($found),
            default => $this->nothing(),
        };
    }

    /**
     * What a filter that creates would do: link the expense (or income) already recorded and free, else create one
     * with the title of the filter.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  BankFilter  $filter  The filter.
     * @param  list<string>  $used  what earlier transactions would take
     * @return array{outcome: BankFilterOutcome, title: ?string, on: ?string, used: ?string}
     */
    private function foreseeCreate(BankTransaction $transaction, BankFilter $filter, array $used): array
    {
        $recorded = $this->duplicates->findAny($transaction, null, $filter, BankDuplicates::idsOf($used, $transaction->kind()));

        if ($recorded !== null && $this->duplicates->isFree($recorded, $transaction)) {
            return $this->linked($recorded);
        }

        return ['outcome' => BankFilterOutcome::Created, 'title' => $this->title($transaction, $filter), 'on' => null, 'used' => null];
    }

    /**
     * The forecast of a link to a payment already recorded.
     *
     * @param  Expense|Income  $payment  The payment.
     * @return array{outcome: BankFilterOutcome, title: ?string, on: ?string, used: ?string}
     */
    private function linked(Expense|Income $payment): array
    {
        return [
            'outcome' => BankFilterOutcome::Linked, 'title' => $payment->label(), 'on' => $payment->date?->toDateString(),
            'used' => ($payment instanceof Expense ? 'expense:' : 'income:').$payment->id,
        ];
    }

    /**
     * The forecast of a transaction that stays to sort.
     *
     * @return array{outcome: BankFilterOutcome, title: ?string, on: ?string, used: ?string}
     */
    private function nothing(): array
    {
        return ['outcome' => BankFilterOutcome::None, 'title' => null, 'on' => null, 'used' => null];
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

        if ($planned === null || ! $this->payable($planned, $transaction)) {
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
        $day = $transaction->booked_on ?? CarbonImmutable::now('Europe/Rome');
        $columns = [
            'title' => $this->title($transaction, $filter),
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
        $other = $this->transferAccount($transaction, $filter);

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
     * Whether the planned item of a filter that pays can be paid with the transaction: it is of the same direction,
     * and did not end before the day of the transaction.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  BankTransaction  $transaction  The transaction.
     */
    private function payable(PlannedExpense|PlannedIncome $planned, BankTransaction $transaction): bool
    {
        $day = (string) $transaction->booked_on?->toDateString();

        return ($planned instanceof PlannedExpense) === ($transaction->kind() === 'expense')
            && ($planned->end_date === null || $planned->end_date->toDateString() >= $day);
    }

    /**
     * The account on the other side of the transfer of a filter: null when it is gone, closed (the filter's *Filters*
     * row says so, and the transaction stays to sort) or the account of the transaction itself.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  BankFilter  $filter  The filter.
     */
    private function transferAccount(BankTransaction $transaction, BankFilter $filter): ?FinancialAccount
    {
        $other = $filter->transferAccount;

        return $other instanceof FinancialAccount && $other->status === FinancialAccountStatus::Active && $other->id !== $transaction->financial_account_id ? $other : null;
    }

    /**
     * The title of the movement a filter creates: its title (else its name), with the month of the transaction when
     * the filter asks.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  BankFilter  $filter  The filter.
     */
    private function title(BankTransaction $transaction, BankFilter $filter): string
    {
        $title = $filter->title !== null && trim($filter->title) !== '' ? $filter->title : $filter->name;

        return $filter->title_with_month ? PaymentTitle::for($title, $transaction->booked_on ?? CarbonImmutable::now('Europe/Rome')) : $title;
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
