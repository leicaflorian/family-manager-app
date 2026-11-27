<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankTransactionStatus;
use App\Models\BankTransaction;
use App\Support\Banks\BankText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pairs a direct debit with its commission: BancoPosta writes the commission of each SEPA direct debit as a
 * transaction of its own with the same mandate and creditor. Same account, same direction, a commission that is
 * smaller than the debit, at most five days apart; the pairs are made closest first, so a commission goes to one
 * debit only and each debit takes one commission.
 */
final class BankFees
{
    /** The most days between a debit and its commission. */
    private const int DAYS = 5;

    /** Seconds in a day, to count the days between two dates. */
    private const int DAY = 86400;

    /**
     * The commission of a direct debit, among the booked transactions of the family (linked or not).
     *
     * @param  BankTransaction  $debit  the direct debit
     */
    public function feeOf(BankTransaction $debit): ?BankTransaction
    {
        if (BankText::isFee($debit->text) || $this->key($debit) === null) {
            return null;
        }

        return ($this->match($this->around($debit))[$debit->id] ?? null)?->fee;
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

        $times = $debits->map(fn (BankTransaction $row): int => $this->time($row));
        $outside = BankTransaction::query()
            ->whereIn('financial_account_id', $debits->pluck('financial_account_id')->unique()->all())
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('amount', '<', 0)
            ->whereBetween('booked_on', [date('Y-m-d', $times->min() - self::DAYS * self::DAY), date('Y-m-d', $times->max() + self::DAYS * self::DAY)])
            ->where(fn (Builder $query) => $query->where('text', 'like', 'COMMISSIONI%')->orWhere('text', 'like', 'COMMISSIONE%'))
            ->whereNotIn('id', $rows->pluck('id')->all())
            ->get();

        return array_map(static fn (object $pair): BankTransaction => $pair->fee, $this->match($rows->concat($outside)));
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
        $time = $this->time($anchor);
        $code = BankText::mandate($anchor->text) ?? BankText::creditor($anchor->text) ?? '';

        return BankTransaction::query()
            ->where('financial_account_id', $anchor->financial_account_id)
            ->where('status', BankTransactionStatus::Booked)
            ->where('currency', 'EUR')
            ->where('amount', $anchor->kind() === 'expense' ? '<' : '>', 0)
            ->whereBetween('booked_on', [date('Y-m-d', $time - 2 * self::DAYS * self::DAY), date('Y-m-d', $time + 2 * self::DAYS * self::DAY)])
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
        $fees = $rows->filter(fn (BankTransaction $row): bool => $this->key($row) !== null && BankText::isFee($row->text));
        $debits = $rows->filter(fn (BankTransaction $row): bool => $this->key($row) !== null && ! BankText::isFee($row->text));
        $edges = [];

        foreach ($debits as $debit) {
            foreach ($fees as $fee) {
                $days = intdiv(abs($this->time($debit) - $this->time($fee)), self::DAY);

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
     * neither a mandate nor a creditor, or no booking day.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function key(BankTransaction $transaction): ?string
    {
        $mandate = BankText::mandate($transaction->text);
        $creditor = BankText::creditor($transaction->text);

        if (($mandate === null && $creditor === null) || ($transaction->booked_on ?? $transaction->value_on) === null) {
            return null;
        }

        return $transaction->financial_account_id.'|'.$transaction->kind().'|'.$mandate.'|'.$creditor;
    }

    /**
     * The booking day as a timestamp (midnight), falling back to the day the bank gave.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function time(BankTransaction $transaction): int
    {
        return ($transaction->booked_on ?? $transaction->value_on)?->getTimestamp() ?? 0;
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
