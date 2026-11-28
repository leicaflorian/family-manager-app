<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\BankTransaction;
use App\Support\Banks\BankGroup;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Puts together the transactions still to sort that are of the same thing, so the person can sort them in one go.
 * A group is made by the mandate of a direct debit, else the counterparty the bank gives, else the text as
 * `BankMatch::signature()` reads it, on one account and in one direction; it has at least two transactions. The
 * commission of a direct debit follows its debit and is in no group, and what a filter takes is left to the filter.
 * Everything is read with a fixed number of queries: the transactions, their accounts, the filters, and at most the commissions.
 */
final readonly class BankGroups
{
    /** How many characters of the hash make the key. */
    private const int KEY_LENGTH = 16;

    /** The most transactions of a family still to sort that are looked at together. */
    private const int LIMIT = 5000;

    /**
     * @param  BankFilters  $filters  which filters take a transaction
     * @param  BankFees  $fees  which commission belongs to which debit
     */
    public function __construct(private BankFilters $filters, private BankFees $fees) {}

    /**
     * The groups of two or more transactions, the biggest first (then by name).
     *
     * @param  array{0: Collection<int, BankTransaction>, 1: list<int>}|null  $read  what `read()` gave, to read the transactions once for several calls
     * @return list<BankGroup>
     */
    public function all(?array $read = null): array
    {
        [$candidates] = $read ?? $this->candidates();

        return array_values(array_filter(
            $this->build($candidates),
            static fn (BankGroup $group): bool => $group->transactions->count() >= 2,
        ));
    }

    /**
     * The group with a key, null when it has fewer than two transactions now.
     *
     * @param  string  $key  the key of `keyOf()`
     * @param  array{0: Collection<int, BankTransaction>, 1: list<int>}|null  $read  what `read()` gave
     */
    public function find(string $key, ?array $read = null): ?BankGroup
    {
        return array_find($this->all($read), static fn (BankGroup $group): bool => $group->key === $key);
    }

    /**
     * The other transactions of the group a transaction belongs to, newest first. Works for a transaction that is
     * sorted already (the panel *I found other movements like this* asks after the person sorted it), and does not
     * need the group to have two: it gives the transactions still to sort with the same key, a rejected one excepted.
     *
     * @param  BankTransaction  $transaction  the transaction
     * @return Collection<int, BankTransaction>
     */
    public function siblings(BankTransaction $transaction): Collection
    {
        [$candidates] = $this->candidates();
        $key = $this->keyOf($transaction);

        $group = $this->build($candidates)[$key] ?? null;

        return ($group instanceof BankGroup ? $group->transactions : new Collection)
            ->reject(static fn (BankTransaction $row): bool => $row->is($transaction))
            ->values();
    }

    /**
     * The transactions still to sort that are in no group, the pending ones too: not the ones of a group, and not the commission of a debit
     * (it goes with the debit). A commission with no debit stays, and so does what a filter takes.
     *
     * @param  array{0: Collection<int, BankTransaction>, 1: list<int>}|null  $read  what `read()` gave
     * @return Builder<BankTransaction>
     */
    public function lone(?array $read = null): Builder
    {
        [$candidates, $paired] = $read ?? $this->candidates();
        $grouped = collect($this->build($candidates))
            ->filter(static fn (BankGroup $group): bool => $group->transactions->count() >= 2)
            ->flatMap(static fn (BankGroup $group): array => $group->transactions->pluck('id')->all());

        // Not `unsorted()`: the pending ones are listed too (they wait, marked), whatever their currency
        return BankTransaction::query()
            ->whereNull('ignored_at')
            ->whereNull('transfer_account_id')
            ->whereNull('expense_id')
            ->whereNull('income_id')
            ->whereNotIn('id', [...$grouped->all(), ...$paired])
            ->latest('booked_on')
            ->latest('id');
    }

    /**
     * What makes the group of a transaction: the hash of the mandate (else the normalised counterparty, else the
     * signature of the text), the direction and the account, cut to 16 characters.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    public function keyOf(BankTransaction $transaction): string
    {
        return substr(sha1(($this->base($transaction) ?? '').'|'.$transaction->kind().'|'.$transaction->financial_account_id), 0, self::KEY_LENGTH);
    }

    /**
     * What the groups are made from, read once: the transactions that can be in a group and the ids of the
     * commissions that follow their debit. A page that asks for `all()`, `lone()` and `find()` passes it to each, so
     * the transactions are read once.
     *
     * @return array{0: Collection<int, BankTransaction>, 1: list<int>}
     */
    public function read(): array
    {
        return $this->candidates();
    }

    /**
     * The transactions that can be in a group, and the ids of the commissions that follow their debit. One query for
     * the transactions, one for the commissions when there are debits; the filters are read once by the service.
     *
     * @return array{0: Collection<int, BankTransaction>, 1: list<int>}
     */
    private function candidates(): array
    {
        $rows = BankTransaction::query()->unsorted()->with('financialAccount')->latest('booked_on')->latest('id')->limit(self::LIMIT)->get();
        $paired = array_values(array_map(static fn (BankTransaction $fee): int => $fee->id, $this->fees->pairs($rows)));

        $candidates = $rows->filter(fn (BankTransaction $row): bool => ! BankText::isFee($row->text)
            && $this->base($row) !== null
            && $this->filters->matching($row)->isEmpty())->values();

        return [$candidates, $paired];
    }

    /**
     * The transactions put in groups by key, a transaction left out of the group it was taken out of.
     *
     * @param  Collection<int, BankTransaction>  $candidates  the transactions that can be in a group
     * @return array<string, BankGroup> by key, the biggest first
     */
    private function build(Collection $candidates): array
    {
        $byKey = [];

        foreach ($candidates as $row) {
            $key = $this->keyOf($row);

            if (! $this->rejects($row, $key)) {
                $byKey[$key][] = $row;
            }
        }

        $groups = [];

        foreach ($byKey as $key => $rows) {
            $first = $rows[0];
            // A key of digits only becomes an integer array key in PHP
            $key = (string) $key;
            $groups[$key] = new BankGroup($key, $this->name($first), $first->kind(), $first->financial_account_id, new Collection($rows));
        }

        uasort($groups, static fn (BankGroup $a, BankGroup $b): int => [$b->transactions->count(), $a->name] <=> [$a->transactions->count(), $b->name]);

        return $groups;
    }

    /**
     * What the group is made of: the mandate, else the normalised counterparty, else the signature. Null when there
     * is nothing (a text of numbers and short words), so the transaction is never grouped with the others like it.
     *
     * @param  BankTransaction  $transaction  the transaction
     */
    private function base(BankTransaction $transaction): ?string
    {
        $mandate = BankText::mandate($transaction->text);

        if ($mandate !== null) {
            return $mandate;
        }

        $counterparty = BankMatch::normalize((string) $transaction->counterparty);

        if ($counterparty !== '') {
            return $counterparty;
        }

        $signature = BankMatch::signature($transaction->text);

        return $signature === '' ? null : $signature;
    }

    /**
     * The name of the group: the counterparty the bank gives, else the words of the text with an initial capital.
     *
     * @param  BankTransaction  $transaction  a transaction of the group
     */
    private function name(BankTransaction $transaction): string
    {
        $counterparty = trim((string) $transaction->counterparty);

        return $counterparty !== '' ? $counterparty : ucwords(mb_strtolower(implode(' ', BankText::words($transaction->text))));
    }

    /**
     * Whether the person took the transaction out of this group (`group:<key>` among its rejected proposals, as a
     * value of the list or as a key).
     *
     * @param  BankTransaction  $transaction  the transaction
     * @param  string  $key  the key of the group
     */
    private function rejects(BankTransaction $transaction, string $key): bool
    {
        $rejected = $transaction->rejected_proposals ?? [];

        return array_key_exists('group:'.$key, $rejected) || in_array('group:'.$key, $rejected, true);
    }
}
