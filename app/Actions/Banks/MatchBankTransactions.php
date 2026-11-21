<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankMatcher;
use App\Support\Banks\BankMatch;
use App\Support\Banks\RuleAccount;
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
     * transaction that fails (also while it is checked or matched) is reported and left to sort, and the others go on.
     *
     * @param  iterable<BankTransaction>  $transactions  The transactions, in the order to settle them.
     * @return int how many paid or were linked to a payment
     */
    public function handle(iterable $transactions): int
    {
        $settled = 0;

        foreach ($transactions as $transaction) {
            $paid = $this->safely(function () use ($transaction): bool {
                $planned = $this->isOpen($transaction) ? $this->matcher->planned($transaction) : null;

                return $planned !== null && $this->settle->handle($transaction, $planned, BankMatchSource::Rule) !== null;
            });

            if ($paid) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * Settles the transactions still to sort and not declined of the last days of a first read (90) that the rule of
     * the planned item recognises on the accounts it reads (`RuleAccount`), the oldest first, so a rule written after the payments pays the due dates left open.
     * A transaction whose rule picks another planned item (closer in amount) is left to that one. A failure is
     * reported and never leaves: the planned item is already saved.
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
        $account = RuleAccount::of($planned->financial_account_id);
        $transactions = $this->safely(static fn () => BankTransaction::query()
            ->unsorted()
            ->whereNull('declined_at')
            ->where('amount', $planned instanceof PlannedExpense ? '<' : '>', 0)
            ->where('booked_on', '>=', $since->toDateString())
            ->when($account !== null, static fn ($query) => $query->where('financial_account_id', $account))
            ->oldest('booked_on')
            ->oldest('id')
            ->get()
            ->filter(static fn (BankTransaction $transaction): bool => BankMatch::matches($rule, $transaction->text))
            ->all(), []);

        $settled = 0;

        foreach ($transactions as $transaction) {
            $paid = $this->safely(fn (): bool => $this->matcher->planned($transaction)?->is($planned) === true
                && $this->settle->handle($transaction, $planned, BankMatchSource::Rule) !== null);

            if ($paid) {
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
     * Runs a piece of the matching; a failure (a database error, a broken rule) is reported and gives the fallback, so
     * it never stops the read of a bank nor the save of a planned item, and a transaction that failed stays to sort.
     *
     * @template T
     *
     * @param  callable(): T  $work  The work.
     * @param  T  $fallback  What a failure gives.
     * @return T
     */
    private function safely(callable $work, mixed $fallback = false): mixed
    {
        try {
            return $work();
        } catch (Throwable $failure) {
            report($failure);

            return $fallback;
        }
    }
}
