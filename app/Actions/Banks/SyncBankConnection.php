<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Exceptions\Banks\BankConsentEnded;
use App\Exceptions\Banks\BankLimitReached;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Support\Banks\BankVisitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the transactions of every account of a bank connection, saves them and lets the rules of the planned items pay
 * with the new booked ones. Runs inside the family of the connection. No error of the bank leaves it: it becomes the
 * status of the connection.
 */
final readonly class SyncBankConnection
{
    /**
     * @param  BankFeed  $feed  the link to the bank
     * @param  StoreBankEntries  $store  saves what the bank gives
     * @param  MatchBankTransactions  $match  pays the due dates the new transactions settle
     * @param  WatchBankConsents  $watch  tells the admins when the bank took the consent back
     */
    public function __construct(private BankFeed $feed, private StoreBankEntries $store, private MatchBankTransactions $match, private WatchBankConsents $watch) {}

    /**
     * Reads the connection. An account with no transactions is read from `first_days` ago, the others from
     * `overlap_days` before the last read. A consent that ended asks the person to connect again, a limit of the bank
     * changes nothing (the next read tries again), as another read saving the same rows at the same moment does, any other failure is reported and saved as the last error. The
     * booked transactions saved before any of these, the 90 days of a first read included, go to the rules, the oldest
     * first.
     *
     * @param  BankConnection  $connection  the connection to read
     * @param  BankVisitor|null  $visitor  who is looking, when a person asked for the read
     * @return int how many booked transactions are new
     */
    public function handle(BankConnection $connection, ?BankVisitor $visitor = null): int
    {
        $fresh = $this->read($connection, $visitor);

        // The oldest first, as a person would sort them: a late due date is paid by the first transaction after it
        $this->match->handle(collect($fresh)->sortBy([
            static fn (BankTransaction $a, BankTransaction $b): int => $a->booked_on?->toDateString() <=> $b->booked_on?->toDateString(),
            static fn (BankTransaction $a, BankTransaction $b): int => $a->id <=> $b->id,
        ])->values()->all());

        return count($fresh);
    }

    /**
     * Reads every account of the connection and saves what the bank gives, changing the status of the connection.
     *
     * @param  BankConnection  $connection  the connection to read
     * @param  BankVisitor|null  $visitor  who is looking, when a person asked for the read
     * @return list<BankTransaction> the booked transactions that are new, in the order they were saved
     */
    private function read(BankConnection $connection, ?BankVisitor $visitor): array
    {
        $session = $connection->sessionId();

        if ($session === null) {
            $this->mark($connection, BankConnectionStatus::NeedsReconnect, 'The session of the bank cannot be read.');

            return [];
        }

        $today = CarbonImmutable::now('Europe/Rome')->startOfDay();
        $new = [];

        try {
            foreach ($connection->accounts()->whereNotNull('bank_account_uid')->orderBy('id')->get() as $account) {
                $hasTransactions = BankTransaction::query()->where('financial_account_id', $account->id)->exists();
                $from = $hasTransactions && $connection->last_synced_at !== null
                    ? $connection->last_synced_at->setTimezone('Europe/Rome')->startOfDay()->subDays(config()->integer('bank_feed.overlap_days'))
                    : $today->subDays(config()->integer('bank_feed.first_days'));

                $entries = $this->feed->transactions($session, (string) $account->bank_account_uid, $from, $today, $visitor);
                $new = [...$new, ...$this->store->handle($connection, $account, $entries)];
            }
        } catch (BankConsentEnded) {
            $this->mark($connection, BankConnectionStatus::NeedsReconnect, 'The consent of the bank ended.');

            try {
                $this->watch->ended($connection);
            } catch (Throwable $failure) {
                // Telling the admins must not turn a consent that ended into an error of the read
                report($failure);
            }

            return $new;
        } catch (BankLimitReached) {
            return $new;
        } catch (UniqueConstraintViolationException) {
            // Another read of the same bank (a job and *Update* at the same moment) saved these rows first: nothing wrong
            // with the bank, and what this read left the next one finds again
            return $new;
        } catch (Throwable $failure) {
            report($failure);
            $this->mark($connection, BankConnectionStatus::Error, Str::limit($failure->getMessage(), 250));

            return $new;
        }

        $connection->forceFill(['status' => BankConnectionStatus::Active, 'last_error' => null, 'last_synced_at' => now()])->save();

        return $new;
    }

    /**
     * Saves where the connection is and why.
     *
     * @param  BankConnection  $connection  the connection
     * @param  BankConnectionStatus  $status  the new status
     * @param  string  $error  what went wrong
     */
    private function mark(BankConnection $connection, BankConnectionStatus $status, string $error): void
    {
        $connection->forceFill(['status' => $status, 'last_error' => $error])->save();
    }
}
