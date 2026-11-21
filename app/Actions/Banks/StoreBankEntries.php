<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankTransactionStatus;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use App\Support\Banks\BankEntry;
use Illuminate\Support\Facades\DB;

/**
 * Saves what a read of an account gave, without ever writing a transaction twice.
 */
final class StoreBankEntries
{
    /** The longest key the column holds; a longer id of the bank is kept as its fingerprint. */
    private const int KEY_LENGTH = 191;

    /**
     * Stores the entries of one read of an account. A booked entry is told apart by the id of the bank, or, when the
     * bank gives none, by day, amount and text plus its order among the identical entries of the same read (two
     * identical coffees on the same day stay two); an id longer than the column is kept as its fingerprint (`key()`).
     * A booked entry already seen is left as it is. The pending entries of the account that are not linked to a payment
     * are removed and written again from what the bank says now.
     *
     * @param  BankConnection  $connection  the connection the read went through
     * @param  FinancialAccount  $account  the account that was read
     * @param  list<BankEntry>  $entries  what the bank gave
     * @return list<BankTransaction> only the booked transactions that were not there before
     */
    public function handle(BankConnection $connection, FinancialAccount $account, array $entries): array
    {
        return DB::transaction(function () use ($connection, $account, $entries): array {
            $booked = [];
            $pending = [];
            $seen = [];

            foreach ($entries as $entry) {
                $hash = sha1(implode('|', [$this->day($entry), number_format($entry->amount, 2, '.', ''), $entry->text]));
                $order = $seen[$hash] = ($seen[$hash] ?? -1) + 1;

                if ($entry->booked) {
                    // Keys carry a prefix so a numeric id never becomes an integer array key
                    $booked['k'.$this->key(($entry->providerId !== null && $entry->providerId !== '') ? $entry->providerId : $hash.'#'.$order)] = $entry;
                } else {
                    $pending['kpending:'.$hash.'#'.$order] = $entry;
                }
            }

            // The pending entries come back from the bank in full at every read: the ones nobody linked are rewritten
            BankTransaction::query()
                ->where('financial_account_id', $account->id)
                ->where('status', BankTransactionStatus::Pending)
                ->whereNull('expense_id')
                ->whereNull('income_id')
                ->delete();

            $known = BankTransaction::query()
                ->where('financial_account_id', $account->id)
                ->whereIn('provider_id', array_map(static fn (string $key): string => substr($key, 1), [...array_keys($booked), ...array_keys($pending)]))
                ->pluck('provider_id')
                ->map(static fn (mixed $id): string => 'k'.(is_scalar($id) ? (string) $id : ''))
                ->flip();

            $new = [];

            foreach ($pending as $key => $entry) {
                if (! $known->has($key)) {
                    $this->create($connection, $account, substr($key, 1), $entry);
                }
            }

            foreach ($booked as $key => $entry) {
                if (! $known->has($key)) {
                    $new[] = $this->create($connection, $account, substr($key, 1), $entry);
                }
            }

            return $new;
        });
    }

    /**
     * The key that tells a transaction apart in its account: the id of the bank as it is, or `h:` and its SHA-1 when it
     * is longer than the column, so a long reference neither breaks the read nor is stored twice.
     *
     * @param  string  $id  the id of the bank, or the fingerprint of an entry without one
     */
    private function key(string $id): string
    {
        return strlen($id) > self::KEY_LENGTH ? 'h:'.sha1($id) : $id;
    }

    /**
     * The day of an entry as text: the day it was booked, else its value day.
     *
     * @param  BankEntry  $entry  the entry
     */
    private function day(BankEntry $entry): string
    {
        return ($entry->bookedOn ?? $entry->valueOn)?->toDateString() ?? '';
    }

    /**
     * Writes one entry.
     *
     * @param  BankConnection  $connection  the connection of the read
     * @param  FinancialAccount  $account  the account
     * @param  string  $key  the key that tells the transaction apart in the account
     * @param  BankEntry  $entry  the entry
     */
    private function create(BankConnection $connection, FinancialAccount $account, string $key, BankEntry $entry): BankTransaction
    {
        return BankTransaction::query()->create([
            'bank_connection_id' => $connection->id,
            'financial_account_id' => $account->id,
            'provider_id' => $key,
            'booked_on' => $entry->bookedOn?->toDateString(),
            'value_on' => $entry->valueOn?->toDateString(),
            'amount' => number_format($entry->amount, 2, '.', ''),
            'currency' => $entry->currency,
            'text' => $entry->text,
            'counterparty' => $entry->counterparty,
            'status' => $entry->booked ? BankTransactionStatus::Booked : BankTransactionStatus::Pending,
        ]);
    }
}
