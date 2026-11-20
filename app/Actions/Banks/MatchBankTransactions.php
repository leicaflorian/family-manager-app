<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankMatcher;
use App\Support\Banks\BankMatch;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Pays with the rules of the planned items: the new transactions of a read of the bank, or the transactions still to
 * sort that a rule just written recognises. Runs in a request and in the queued read of a bank.
 */
final readonly class MatchBankTransactions
{
    public function __construct(private BankMatcher $matcher, private SettleFromBank $settle) {}

    /**
     * Settles every transaction still to sort and not declined with the planned item whose rule recognises it. A
     * transaction that fails is reported and left to sort, and the others go on.
     *
     * @param  iterable<BankTransaction>  $transactions  The transactions, in the order to settle them.
     * @return int how many paid or were linked to a payment
     */
    public function handle(iterable $transactions): int
    {
        $settled = 0;

        foreach ($transactions as $transaction) {
            if (! $this->isOpen($transaction)) {
                continue;
            }

            $planned = $this->matcher->planned($transaction);

            if ($planned !== null && $this->settle($transaction, $planned)) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * Settles the transactions still to sort and not declined of the last days of a first read (90) that the rule of
     * the planned item recognises, the oldest first, so a rule written after the payments pays the due dates left open.
     * A transaction whose rule picks another planned item (closer in amount) is left to that one.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item, with its rule saved.
     * @return int how many paid or were linked to a payment
     */
    public function forPlanned(PlannedExpense|PlannedIncome $planned): int
    {
        $rule = (string) $planned->bank_match;

        if ($rule === '') {
            return 0;
        }

        $since = CarbonImmutable::now('Europe/Rome')->startOfDay()->subDays(config()->integer('bank_feed.first_days'));
        $transactions = BankTransaction::query()
            ->unsorted()
            ->whereNull('declined_at')
            ->where('amount', $planned instanceof PlannedExpense ? '<' : '>', 0)
            ->where('booked_on', '>=', $since->toDateString())
            ->when($planned->financial_account_id !== null, static fn ($query) => $query->where('financial_account_id', $planned->financial_account_id))
            ->oldest('booked_on')
            ->oldest('id')
            ->get()
            ->filter(static fn (BankTransaction $transaction): bool => BankMatch::matches($rule, $transaction->text));

        $settled = 0;

        foreach ($transactions as $transaction) {
            if ($this->matcher->planned($transaction)?->is($planned) === true && $this->settle($transaction, $planned)) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * Whether a transaction can still be settled: booked, in euros, not linked, not put aside, not declined.
     *
     * @param  BankTransaction  $transaction  The transaction.
     */
    private function isOpen(BankTransaction $transaction): bool
    {
        return BankTransaction::query()->unsorted()->whereNull('declined_at')->whereKey($transaction->id)->exists();
    }

    /**
     * Settles one transaction with the rule; a failure is reported and the transaction stays to sort.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item its rule recognises.
     * @return bool whether it paid or was linked to a payment
     */
    private function settle(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned): bool
    {
        try {
            return $this->settle->handle($transaction, $planned, BankMatchSource::Rule) !== null;
        } catch (Throwable $failure) {
            report($failure);

            return false;
        }
    }
}
