<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Data\BankProposalData;
use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\Banks\BankFilters;
use App\Services\Banks\BankProposals;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankText;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * *Yes* on «Maybe it is…»: does what the proposal of a lone movement says. It links the expense or income already
 * recorded, pays the due date (`SettleFromBank`, `Proposed`) or marks the transfer; after a due date it saves the filter
 * that pays it from then on, when the planned item has none and the words of the movement take nothing else.
 */
final readonly class AcceptBankProposal
{
    /** The days back the words of the new filter are tried on. */
    private const int FILTER_DAYS = 90;

    public function __construct(
        private BankProposals $proposals,
        private LinkRecordedMovement $link,
        private SettleFromBank $settle,
        private MarkBankTransfer $transfer,
        private BankFilters $filters,
    ) {}

    /**
     * Accepts the proposal, in one transaction. The movement of the bank is locked first and checked again to be still
     * to sort, then the planned item, expense, income or account of the key, and only then the proposal is worked out
     * again: a key that is not among its candidates any more (linked meanwhile, paid, rejected, gone) is refused. So two
     * *Yes* at the same moment wait for each other and the second one finds the movement sorted.
     *
     * @param  BankTransaction  $transaction  the movement of the bank
     * @param  string  $key  the key of the proposal (`BankProposalData::$key`)
     * @param  User  $user  who said yes: the filter is saved only when they can create one that pays a due date
     * @return array{payment: Expense|Income|null, filter: BankFilter|null} the expense or income now linked (null for a transfer) and the filter saved
     *
     * @throws ValidationException (`key`) when the key is not, or not any more, a proposal of the movement
     */
    public function handle(BankTransaction $transaction, string $key, User $user): array
    {
        $parsed = self::parse($key) ?? throw $this->stale();

        [$payment, $planned] = DB::transaction(function () use ($transaction, $key, $parsed): array {
            $current = BankTransaction::query()->unsorted()->whereKey($transaction->id)->lockForUpdate()->first() ?? throw $this->stale();
            $target = $this->lockTarget($parsed);

            if (! in_array($key, array_map(static fn (BankProposalData $proposal): string => $proposal->key, $this->proposals->candidates($current)), true)) {
                throw $this->stale();
            }

            return match (true) {
                $target instanceof Expense, $target instanceof Income => [$this->link->handle($current, $target), null],
                $target instanceof FinancialAccount => $this->markTransfer($current, $target),
                default => [$this->settle->handle($current, $target, BankMatchSource::Proposed, CarbonImmutable::parse((string) $parsed['date'])) ?? throw $this->stale(), $target],
            };
        });

        $filter = $planned !== null && $payment !== null ? $this->saveFilter($transaction->refresh(), $planned, $user) : null;

        return ['payment' => $payment, 'filter' => $filter];
    }

    /**
     * What a key says: the kind (`expense`, `income`, `planned_expense`, `planned_income`, `transfer`), the id and,
     * for a due date, the date the rules give. Null for a text that is not a key.
     *
     * @param  string  $key  the key
     * @return array{type: string, id: string, date: string|null}|null
     */
    public static function parse(string $key): ?array
    {
        if (preg_match('/^(expense|income|transfer):(\d{1,18})$/', $key, $found) === 1) {
            return ['type' => $found[1], 'id' => $found[2], 'date' => null];
        }

        if (preg_match('/^(planned_expense|planned_income):(\d{1,18}):(\d{4}-\d{2}-\d{2})$/', $key, $found) === 1 && CarbonImmutable::canBeCreatedFromFormat($found[3], 'Y-m-d')) {
            return ['type' => $found[1], 'id' => $found[2], 'date' => $found[3]];
        }

        return null;
    }

    /**
     * Locks what the key names, right after the movement and before any plain read: the planned item (as
     * `PlannedKind::findAndLock()`), the expense or income; the account of a transfer is only read.
     *
     * @param  array{type: string, id: string, date: string|null}  $parsed  the key
     *
     * @throws ValidationException (`key`) when the family has no such row
     */
    private function lockTarget(array $parsed): Expense|Income|PlannedExpense|PlannedIncome|FinancialAccount
    {
        try {
            return match ($parsed['type']) {
                'expense' => Expense::query()->lockForUpdate()->findOrFail($parsed['id']),
                'income' => Income::query()->lockForUpdate()->findOrFail($parsed['id']),
                'transfer' => FinancialAccount::query()->findOrFail($parsed['id']),
                'planned_expense' => PlannedKind::findAndLock('expense', $parsed['id']),
                default => PlannedKind::findAndLock('income', $parsed['id']),
            };
        } catch (ModelNotFoundException) {
            throw $this->stale();
        }
    }

    /**
     * Marks the movement as a transfer with the account.
     *
     * @param  BankTransaction  $transaction  the movement, locked
     * @param  FinancialAccount  $account  the account on the other side
     * @return array{0: null, 1: null}
     */
    private function markTransfer(BankTransaction $transaction, FinancialAccount $account): array
    {
        $this->transfer->handle($transaction, $account);

        return [null, null];
    }

    /**
     * Saves the filter *pays the due date* of the planned item with the words `BankText::words()` proposes for the
     * movement, on its account and in its direction, when: who said yes can create it and change the planned item,
     * the planned item has no filter nor rule, the words are safe, and over the last 90 days they take, apart from this
     * movement, only the ones already linked to a payment of the same planned item, never a commission. The check and
     * the saving run in a short transaction that locks the planned item first, so two *Yes* on two movements of the
     * same item save one filter.
     *
     * @param  BankTransaction  $transaction  the movement, now linked
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item it paid
     * @param  User  $user  who said yes
     */
    private function saveFilter(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, User $user): ?BankFilter
    {
        if (! Gate::forUser($user)->allows('create', [BankFilter::class, BankFilterAction::Pay]) || ! Gate::forUser($user)->allows('update', $planned)) {
            return null;
        }

        $criteria = new BankFilterCriteria(
            contains: BankText::words($transaction->text),
            direction: $transaction->kind(),
            accountId: $transaction->financial_account_id,
        );

        if (! $criteria->isSafe()) {
            return null;
        }

        return DB::transaction(function () use ($transaction, $planned, $criteria): ?BankFilter {
            PlannedKind::lock($planned);
            $isExpense = $planned instanceof PlannedExpense;
            $column = $isExpense ? 'planned_expense_id' : 'planned_income_id';
            $current = $planned->newQuery()->find($planned->id);

            if ($current === null || filled($current->bank_match) || BankFilter::query()->where($column, $planned->id)->exists()) {
                return null;
            }

            $payments = $isExpense
                ? Expense::query()->where('planned_expense_id', $planned->id)->pluck('id')->all()
                : Income::query()->where('planned_income_id', $planned->id)->pluck('id')->all();
            $others = $this->filters->taken($criteria, self::FILTER_DAYS, except: $transaction->id)->filter(
                static fn (BankTransaction $row): bool => BankText::isFee($row->text) || ! in_array($isExpense ? $row->expense_id : $row->income_id, $payments, true),
            );

            if ($others->isNotEmpty()) {
                return null;
            }

            return BankFilter::query()->create([
                'name' => $planned->displayTitle(),
                'contains' => $criteria->contains,
                'direction' => $criteria->direction,
                'financial_account_id' => $transaction->financial_account_id,
                'action' => BankFilterAction::Pay,
                $column => $planned->id,
            ]);
        });
    }

    /**
     * The error of a key that is not a proposal of the movement (any more).
     */
    private function stale(): ValidationException
    {
        return ValidationException::withMessages(['key' => __('This proposal is not valid any more: look at the movement again.')]);
    }
}
