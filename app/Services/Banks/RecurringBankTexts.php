<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankTransactionStatus;
use App\Models\BankTransaction;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Finds the transactions still to sort whose text comes back every month: the same text (as `BankMatch::signature()`
 * sees it), the same direction and the same account, three calendar months in a row, each amount within 20% of the
 * first. Reads the family's transactions of the last four months once, in one query, and groups them in memory.
 */
final class RecurringBankTexts
{
    /** The months back (the current one included) the transactions are read from. */
    private const int MONTHS = 4;

    /** How many months in a row make a text recurring. */
    private const int RUN = 3;

    /** The most an amount may differ from the first one, as a part of it (one in five). */
    private const int TOLERANCE_DIVISOR = 5;

    /**
     * The groups of transactions of the last four months, by text, direction and account, each as the booked ones of
     * every month. Null until the first ask.
     *
     * @var array<string, list<BankTransaction>>|null
     */
    private ?array $groups = null;

    /**
     * Whether the text of a transaction comes back every month on its account and in its direction: the same key
     * `chain()` uses, so a row that says yes always has a run to start the planned form from.
     *
     * @param  BankTransaction  $transaction  A transaction of the family.
     */
    public function recurs(BankTransaction $transaction): bool
    {
        return $transaction->status === BankTransactionStatus::Booked
            && $this->chain($transaction) !== null;
    }

    /**
     * The three transactions, oldest first, that make the text of a transaction recurring: the run that contains it,
     * else the one of its month, else the latest run. Null when its text does not come back every month.
     *
     * @param  BankTransaction  $transaction  A booked transaction of the family.
     * @return list<BankTransaction>|null
     */
    public function chain(BankTransaction $transaction): ?array
    {
        $transactions = $this->groups()[$this->key($transaction)] ?? [];
        $chains = $this->chains($transactions);

        if ($chains === []) {
            return null;
        }

        $month = $this->month($transaction);

        foreach ($chains as $chain) {
            if (collect($chain)->contains(static fn (BankTransaction $row): bool => $row->is($transaction))) {
                return $chain;
            }
        }

        foreach ($chains as $chain) {
            if (collect($chain)->contains(fn (BankTransaction $row): bool => $this->month($row) === $month)) {
                return $chain;
            }
        }

        return $chains[array_key_last($chains)];
    }

    /**
     * The transactions of the family still to sort in the last four calendar months, grouped by their key.
     *
     * @return array<string, list<BankTransaction>>
     */
    private function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $since = CarbonImmutable::today('Europe/Rome')->subMonths(self::MONTHS - 1)->startOfMonth();
        $groups = [];

        $rows = BankTransaction::query()
            ->unsorted()
            ->where('booked_on', '>=', $since->toDateString())
            ->oldest('booked_on')
            ->oldest('id')
            ->get();

        foreach ($rows as $row) {
            if (BankMatch::signature($row->text) !== '' && ! BankText::isFee($row->text)) {
                $groups[$this->key($row)][] = $row;
            }
        }

        return $this->groups = $groups;
    }

    /**
     * The runs of three months in a row in a group, oldest first, that a saved planned item could pay: each month has
     * a transaction within 20% of the amount of the first, and the first is not older than the days of a first read.
     *
     * @param  list<BankTransaction>  $transactions  The booked transactions of one group, oldest first.
     * @return list<list<BankTransaction>>
     */
    private function chains(array $transactions): array
    {
        $byMonth = [];

        foreach ($transactions as $transaction) {
            $byMonth[$this->month($transaction)][] = $transaction;
        }

        // The days a saved planned item can still pay: the matcher's reach of a first read (`bank_feed.first_days`)
        $reach = CarbonImmutable::today('Europe/Rome')->subDays(Config::integer('bank_feed.first_days'))->toDateString();
        $chains = [];

        foreach ($byMonth as $month => $firsts) {
            $first = $firsts[0];
            $chain = [$first];

            if ((string) $first->booked_on?->toDateString() < $reach) {
                continue;
            }

            for ($step = 1; $step < self::RUN; $step++) {
                $next = collect($byMonth[$month + $step] ?? [])->first(fn (BankTransaction $row): bool => $this->near($first, $row));

                if (! $next instanceof BankTransaction) {
                    continue 2;
                }

                $chain[] = $next;
            }

            $chains[] = $chain;
        }

        return $chains;
    }

    /**
     * Whether an amount is within 20% of the first one, in cents.
     *
     * @param  BankTransaction  $first  The first transaction of the run.
     * @param  BankTransaction  $other  The one compared with it.
     */
    private function near(BankTransaction $first, BankTransaction $other): bool
    {
        $base = $this->cents($first);

        return abs($this->cents($other) - $base) * self::TOLERANCE_DIVISOR <= $base;
    }

    /**
     * The amount in cents, without the sign.
     *
     * @param  BankTransaction  $transaction  The transaction.
     */
    private function cents(BankTransaction $transaction): int
    {
        return (int) round(abs((float) $transaction->amount) * 100);
    }

    /**
     * The month of the booking as a number that grows by one every month.
     *
     * @param  BankTransaction  $transaction  A booked transaction.
     */
    private function month(BankTransaction $transaction): int
    {
        $day = $transaction->booked_on ?? CarbonImmutable::today('Europe/Rome');

        return $day->year * 12 + $day->month;
    }

    /**
     * What makes two transactions of the same thing: the text, the direction and the account.
     *
     * @param  BankTransaction  $transaction  The transaction.
     */
    private function key(BankTransaction $transaction): string
    {
        return BankMatch::signature($transaction->text).'|'.$transaction->kind().'|'.$transaction->financial_account_id;
    }
}
