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
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the transactions of every account of a bank connection and saves them. Runs inside the family of the
 * connection. No error of the bank leaves it: it becomes the status of the connection.
 */
final readonly class SyncBankConnection
{
    /**
     * @param  BankFeed  $feed  the link to the bank
     * @param  StoreBankEntries  $store  saves what the bank gives
     */
    public function __construct(private BankFeed $feed, private StoreBankEntries $store) {}

    /**
     * Reads the connection. An account with no transactions is read from `first_days` ago, the others from
     * `overlap_days` before the last read. A consent that ended asks the person to connect again, a limit of the bank
     * changes nothing (the next read tries again), any other failure is reported and saved as the last error.
     *
     * @param  BankConnection  $connection  the connection to read
     * @param  BankVisitor|null  $visitor  who is looking, when a person asked for the read
     * @return int how many booked transactions are new
     */
    public function handle(BankConnection $connection, ?BankVisitor $visitor = null): int
    {
        $session = $connection->sessionId();

        if ($session === null) {
            $this->mark($connection, BankConnectionStatus::NeedsReconnect, 'The session of the bank cannot be read.');

            return 0;
        }

        $today = CarbonImmutable::now('Europe/Rome')->startOfDay();
        $new = 0;

        try {
            foreach ($connection->accounts()->whereNotNull('bank_account_uid')->orderBy('id')->get() as $account) {
                $hasTransactions = BankTransaction::query()->where('financial_account_id', $account->id)->exists();
                $from = $hasTransactions && $connection->last_synced_at !== null
                    ? $connection->last_synced_at->setTimezone('Europe/Rome')->startOfDay()->subDays(config()->integer('bank_feed.overlap_days'))
                    : $today->subDays(config()->integer('bank_feed.first_days'));

                $entries = $this->feed->transactions($session, (string) $account->bank_account_uid, $from, $today, $visitor);
                $new += count($this->store->handle($connection, $account, $entries));
            }
        } catch (BankConsentEnded) {
            $this->mark($connection, BankConnectionStatus::NeedsReconnect, 'The consent of the bank ended.');

            return $new;
        } catch (BankLimitReached) {
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
