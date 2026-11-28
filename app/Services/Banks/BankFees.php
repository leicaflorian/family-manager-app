<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankFilterFee;
use App\Enums\BankMatchSource;
use App\Enums\BankTransactionStatus;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\BankText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Pairs a direct debit with its commission: BancoPosta writes the commission of each SEPA direct debit as a
 * transaction of its own with the same mandate and creditor. Same account, same direction, a commission that is
 * smaller than the debit, at most five days apart; the pairs are made closest first, so a commission goes to one
 * debit only and each debit takes one commission. A filter can recognise the commission with its own words instead
 * (`words`): those pairs are made one debit at a time, when the debit is linked or the commission arrives, not
 * closest first over all the debits; a commission already joined to another expense is not taken again.
 */
final class BankFees
{
    /** The most days between a debit and its commission. */
    private const int DAYS = 5;

    /**
     * The filters that recognise the commission with words, loaded on first use.
     *
     * @var EloquentCollection<int, BankFilter>|null
     */
    private ?EloquentCollection $wordFilters = null;

    /**
     * The commission of a direct debit, among the booked transactions of the family (linked or not). The filter that
     * takes the debit says how to find it: by itself (`auto`, also without a filter: the same mandate and creditor),
     * with its own words (`words`: on the same account, in the same direction, within five days, smaller than the
     * debit, not taken by another expense, the closest), or not at all (`none`).
     *
     * @param  BankTransaction  $debit  the direct debit
     * @param  BankFilter|null  $filter  the filter that takes the debit
     */
    public function feeOf(BankTransaction $debit, ?BankFilter $filter = null): ?BankTransaction
    {
        $mode = $filter->fee_mode ?? BankFilterFee::Auto;

        if (BankText::isFee($debit->text) || $mode === BankFilterFee::None) {
            return null;
        }

        if ($filter instanceof BankFilter && $mode === BankFilterFee::Words) {
            return $this->byWords($debit, $filter);
        }

        return $this->key($debit) === null ? null : ($this->match($this->around($debit))[$debit->id] ?? null)?->fee;
    }

    /**
     * The debit a commission belongs to by the words of a filter: a debit already linked to an expense (not a
     * commission itself) by a filter that recognises its commission with words (`words`), whose words take this
     * commission (`takesAsFee()`), and whose expense has no commission yet; the closest, then the oldest row. This is
     * how a commission that arrives at a read after its debit joins it.
     *
     * @param  BankTransaction  $fee  the commission, just read
     */
    public function debitByWords(BankTransaction $fee): ?BankTransaction
    {
        $filters = $this->wordFilters();

        if ($filters->isEmpty() || $fee->status !== BankTransactionStatus::Booked || $fee->currency !== 'EUR' || ($fee->booked_on ?? $fee->value_on) === null) {
            return null;
        }

        $day = $this->day($fee);

        /** @var Builder<BankTransaction> $query */
        $query = BankTransaction::query()->withoutFees();

        return $query
            ->whereKeyNot($fee->id)
            ->where('financial_account_id', $fee->financial_account_id)
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('amount', $fee->kind() === 'expense' ? '<' : '>', 0)
            ->whereBetween('booked_on', [$day->subDays(self::DAYS)->toDateString(), $day->addDays(self::DAYS)->toDateString()])
            ->whereNotNull('expense_id')
            ->whereIn('bank_filter_id', $filters->keys()->all())
            ->whereNotIn('expense_id', BankTransaction::query()->where('matched_by', BankMatchSource::Fee)->whereNotNull('expense_id')->select('expense_id'))
            ->orderBy('id')
            ->get()
            ->filter(function (BankTransaction $debit) use ($filters, $fee): bool {
                $filter = $filters->get((int) $debit->bank_filter_id);

                return $filter instanceof BankFilter && $this->takesAsFee($filter, $debit, $fee);
            })
            ->sortBy(fn (BankTransaction $debit): int => abs($this->index($debit) - $this->index($fee)))
            ->first();
    }

    /**
     * Whether a filter that recognises the commission with words (`words`) takes a transaction as the commission of
     * a debit: its commission words take it (on the account and in the direction of the debit), it is not the debit
     * itself, it is at most five days from the debit and smaller than it. The one
     * rule for both ways: the commission of a debit (`feeOf()`) and the debit of a commission (`debitByWords()`).
     *
     * @param  BankFilter  $filter  the filter that took the debit
     * @param  BankTransaction  $debit  the debit
     * @param  BankTransaction  $fee  the transaction that may be its commission
     */
    public function takesAsFee(BankFilter $filter, BankTransaction $debit, BankTransaction $fee): bool
    {
        if ($filter->fee_mode !== BankFilterFee::Words || $fee->is($debit)
            || ($debit->booked_on ?? $debit->value_on) === null || ($fee->booked_on ?? $fee->value_on) === null) {
            return false;
        }

        $criteria = BankFilterCriteria::fromArray([
            'contains' => $filter->fee_contains,
            'excludes' => $filter->fee_excludes,
            'direction' => $debit->kind(),
            'accountId' => $debit->financial_account_id,
        ]);

        return abs($this->index($fee) - $this->index($debit)) <= self::DAYS
            && $this->cents($fee) < $this->cents($debit)
            && BankFilterMatch::matches($criteria, $fee);
    }

    /**
     * The amounts in cents to compare a debit with an expense: the debit plus its commission when the commission is
     * there, else the debit alone.
     *
     * @param  BankTransaction  $debit  the debit
     * @param  BankFilter|null  $filter  the filter that takes the debit, which says how its commission is found
     * @return list<int>
     */
    public function amounts(BankTransaction $debit, ?BankFilter $filter = null): array
    {
        $fee = $this->feeOf($debit, $filter);

        return [$this->cents($debit) + ($fee instanceof BankTransaction ? $this->cents($fee) : 0)];
    }

    /**
     * Whether the bank writes (or can write) the commission of a debit as a transaction of its own: the commission is
     * there, or the debit has a mandate and its commission can still come. A filter that recognises the commission
     * with its words says it is written apart; one without a commission says it is not.
     *
     * @param  BankTransaction  $debit  the debit
     * @param  BankFilter|null  $filter  the filter that takes the debit
     */
    public function isDetached(BankTransaction $debit, ?BankFilter $filter = null): bool
    {
        return match ($filter->fee_mode ?? BankFilterFee::Auto) {
            BankFilterFee::None => false,
            BankFilterFee::Words => true,
            BankFilterFee::Auto => ! BankText::isFee($debit->text) && (BankText::mandate($debit->text) !== null || $this->feeOf($debit) instanceof BankTransaction),
        };
    }

    /**
     * The direct debit a commission belongs to. By default only a debit still to sort counts, so a commission whose
     * debit is linked (or put aside) is an orphan and gives null; `includeSorted` finds the debit wherever it is.
     *
     * @param  BankTransaction  $fee  the commission
     * @param  bool  $includeSorted  also a debit that is linked or ignored
     */
    public function debitOf(BankTransaction $fee, bool $includeSorted = false): ?BankTransaction
    {
        if (! BankText::isFee($fee->text) || $this->key($fee) === null) {
            return null;
        }

        foreach ($this->match($this->around($fee)) as $pair) {
            if ($pair->fee->is($fee)) {
                return $includeSorted || $this->isUnsorted($pair->debit) ? $pair->debit : null;
            }
        }

        return null;
    }

    /**
     * The commissions of the debits in a collection, found with one query at most: the commissions that are not in
     * the collection are read together. A commission in the collection that is the value of no debit is an orphan.
     *
     * @param  Collection<int, BankTransaction>  $rows  the transactions on show
     * @return array<int, BankTransaction> the id of the debit to its commission
     */
    public function pairs(Collection $rows): array
    {
        $debits = $rows->filter(fn (BankTransaction $row): bool => $this->key($row) !== null && ! BankText::isFee($row->text));

        if ($debits->isEmpty()) {
            return [];
        }

        $outside = $this->outside($rows, $debits);

        return array_map(static fn (object $pair): BankTransaction => $pair->fee, $this->match($rows->concat($outside)));
    }

    /**
     * What a block of unsorted rows shows about commissions, with `debitOf()` as the only judge: a commission whose
     * debit is still to sort is hidden from its own block (it is not a row) and shown under that debit, wherever the
     * debit is; a commission with no debit to sort stays a row, an orphan. The commissions that sit outside the block
     * but near its debits are read with one query.
     *
     * @param  Collection<int, BankTransaction>  $rows  the transactions on show
     * @return array{fees: array<int, BankTransaction>, hidden: list<int>, orphans: list<int>} the commission of each debit on show (by the id of the debit), the ids of the commissions to leave out, the ids of the orphans
     */
    public function onPage(Collection $rows): array
    {
        $debits = $rows->filter(fn (BankTransaction $row): bool => $this->key($row) !== null && ! BankText::isFee($row->text));
        $shown = $rows->pluck('id')->flip();
        $onShow = $debits->pluck('id')->flip();
        $fees = [];
        $hidden = [];
        $orphans = [];
        $candidates = $rows->filter(fn (BankTransaction $row): bool => BankText::isFee($row->text))
            ->concat($debits->isEmpty() ? [] : $this->outside($rows, $debits));

        foreach ($candidates as $fee) {
            $debit = $this->debitOf($fee);

            if ($debit instanceof BankTransaction && $onShow->has($debit->id)) {
                $fees[$debit->id] = $fee;
            }

            if (! $shown->has($fee->id)) {
                continue;
            }

            if ($debit instanceof BankTransaction) {
                $hidden[] = $fee->id;
            } else {
                $orphans[] = $fee->id;
            }
        }

        return ['fees' => $fees, 'hidden' => $hidden, 'orphans' => $orphans];
    }

    /**
     * The commission of a debit found with the words of its filter: a booked euro transaction on the same account and
     * in the same direction, at most five days from the debit, smaller than it, that the words of the commission take,
     * still to sort or already joined to the expense of this debit; the closest wins (then the oldest row).
     *
     * @param  BankTransaction  $debit  the debit
     * @param  BankFilter  $filter  the filter, with `fee_contains` and `fee_excludes`
     */
    private function byWords(BankTransaction $debit, BankFilter $filter): ?BankTransaction
    {
        if (($debit->booked_on ?? $debit->value_on) === null) {
            return null;
        }

        $day = $this->day($debit);

        return BankTransaction::query()
            ->whereKeyNot($debit->id)
            ->where('financial_account_id', $debit->financial_account_id)
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('amount', $debit->kind() === 'expense' ? '<' : '>', 0)
            ->whereBetween('booked_on', [$day->subDays(self::DAYS)->toDateString(), $day->addDays(self::DAYS)->toDateString()])
            ->whereNull('ignored_at')
            ->whereNull('transfer_account_id')
            ->whereNull('income_id')
            ->where(static fn (Builder $query) => $query->whereNull('expense_id')
                ->when($debit->expense_id !== null, static fn (Builder $query) => $query->orWhere(static fn (Builder $query) => $query
                    ->where('expense_id', $debit->expense_id)->where('matched_by', BankMatchSource::Fee))))
            ->orderBy('id')
            ->get()
            ->filter(fn (BankTransaction $row): bool => $this->takesAsFee($filter, $debit, $row))
            ->sortBy(fn (BankTransaction $row): int => abs($this->index($row) - $this->index($debit)))
            ->first();
    }

    /**
     * The filters of the family that recognise the commission with words, by id, read once per instance.
     *
     * @return EloquentCollection<int, BankFilter>
     */
    private function wordFilters(): EloquentCollection
    {
        return $this->wordFilters ??= BankFilter::query()->where('fee_mode', BankFilterFee::Words)->get()->keyBy('id');
    }

    /**
     * The commissions near the debits of a block that are not in the block, with one query.
     *
     * @param  Collection<int, BankTransaction>  $rows  the transactions on show
     * @param  Collection<int, BankTransaction>  $debits  the direct debits among them
     * @return Collection<int, BankTransaction>
     */
    private function outside(Collection $rows, Collection $debits): Collection
    {
        $days = $debits->map(fn (BankTransaction $row): CarbonImmutable => $this->day($row));

        return BankTransaction::query()
            ->whereIn('financial_account_id', $debits->pluck('financial_account_id')->unique()->all())
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('amount', '<', 0)
            ->whereBetween('booked_on', [$days->min()?->subDays(self::DAYS)->toDateString(), $days->max()?->addDays(self::DAYS)->toDateString()])
            ->where(fn (Builder $query) => $query->where('text', 'like', 'COMMISSIONI%')->orWhere('text', 'like', 'COMMISSIONE%'))
            ->whereNotIn('id', $rows->pluck('id')->all())
            ->get();
    }

    /**
     * The booked transactions of the family that can take part in the pairing of one transaction: its account and
     * direction, its mandate or creditor, ten days either way (a competitor of a candidate is five days from it).
     *
     * @param  BankTransaction  $anchor  a debit or a commission
     * @return Collection<int, BankTransaction>
     */
    private function around(BankTransaction $anchor): Collection
    {
        $day = $this->day($anchor);
        $code = BankText::mandate($anchor->text) ?? BankText::creditor($anchor->text) ?? '';

        return BankTransaction::query()
            ->where('financial_account_id', $anchor->financial_account_id)
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('amount', $anchor->kind() === 'expense' ? '<' : '>', 0)
            ->whereBetween('booked_on', [$day->subDays(2 * self::DAYS)->toDateString(), $day->addDays(2 * self::DAYS)->toDateString()])
            ->where('text', 'like', '%'.addcslashes($code, '%_\\').'%')
            ->get();
    }

    /**
     * The pairs of a set of transactions, made closest first (then by id): a debit and a commission of the same key,
     * the commission smaller than the debit and at most five days away; each is used once.
     *
     * @param  Collection<int, BankTransaction>  $rows  debits and commissions
     * @return array<int, object{debit: BankTransaction, fee: BankTransaction}> by the id of the debit
     */
    private function match(Collection $rows): array
    {
        $rows = $rows->filter(fn (BankTransaction $row): bool => $this->key($row) !== null);
        $fees = $rows->filter(fn (BankTransaction $row): bool => BankText::isFee($row->text));
        $debits = $rows->reject(fn (BankTransaction $row): bool => BankText::isFee($row->text));
        $edges = [];

        foreach ($debits as $debit) {
            foreach ($fees as $fee) {
                $days = abs($this->index($debit) - $this->index($fee));

                if ($this->key($debit) === $this->key($fee) && $days <= self::DAYS && $this->cents($fee) < $this->cents($debit)) {
                    $edges[] = [$days, $debit->id, $fee->id, $debit, $fee];
                }
            }
        }

        usort($edges, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        $pairs = [];
        $used = [];

        foreach ($edges as [, $debitId, $feeId, $debit, $fee]) {
            if (isset($pairs[$debitId]) || isset($used[$feeId])) {
                continue;
            }

            $used[$feeId] = true;
            $pairs[$debitId] = (object) ['debit' => $debit, 'fee' => $fee];
        }

        return $pairs;
    }

    /**
     * What a debit and its commission share: account, direction, mandate and creditor. Null for a transaction that has
     * neither a mandate nor a creditor, no booking day, or that is not booked and in euros.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function key(BankTransaction $transaction): ?string
    {
        $mandate = BankText::mandate($transaction->text);
        $creditor = BankText::creditor($transaction->text);

        if (($mandate === null && $creditor === null) || ($transaction->booked_on ?? $transaction->value_on) === null
            || $transaction->status !== BankTransactionStatus::Booked || $transaction->currency !== 'EUR') {
            return null;
        }

        return $transaction->financial_account_id.'|'.$transaction->kind().'|'.$mandate.'|'.$creditor;
    }

    /**
     * The booking day (falling back to the day the bank gave) as a calendar date at midnight.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function day(BankTransaction $transaction): CarbonImmutable
    {
        return CarbonImmutable::parse(($transaction->booked_on ?? $transaction->value_on)?->toDateString() ?? 'today', 'UTC');
    }

    /**
     * The calendar day as a whole number that grows by one a day, so a clock change cannot shift a difference.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function index(BankTransaction $transaction): int
    {
        return intdiv($this->day($transaction)->getTimestamp(), 86400);
    }

    /**
     * The amount in cents, without the sign.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function cents(BankTransaction $transaction): int
    {
        return (int) round(abs((float) $transaction->amount) * 100);
    }

    /**
     * Whether a transaction is still to sort: not linked and not put aside.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function isUnsorted(BankTransaction $transaction): bool
    {
        return $transaction->expense_id === null && $transaction->income_id === null && $transaction->ignored_at === null;
    }
}
