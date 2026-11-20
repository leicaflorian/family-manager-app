<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\BankFeed;
use App\Exceptions\Banks\BankFeedFailed;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;

/**
 * Disconnects a bank: closes the permission at the provider (the connection goes even when the provider does not
 * answer), unties the accounts, deletes the transactions that pay nothing and keeps the ones tied to an expense or an
 * income, then deletes the connection. Runs inside the family of the connection.
 */
final readonly class DisconnectBank
{
    public function __construct(private BankFeed $feed) {}

    /**
     * @param  BankConnection  $connection  the connection to remove
     */
    public function handle(BankConnection $connection): void
    {
        $session = $connection->sessionId();

        if ($session !== null) {
            try {
                $this->feed->revoke($session);
            } catch (BankFeedFailed $failure) {
                // The person wants the bank gone: the consent ends by itself at its date
                report($failure);
            }
        }

        DB::transaction(function () use ($connection): void {
            FinancialAccount::query()->where('bank_connection_id', $connection->id)->update([
                'bank_connection_id' => null,
                'bank_account_key' => null,
                'bank_account_uid' => null,
            ]);

            $transactions = BankTransaction::query()->where('bank_connection_id', $connection->id);
            (clone $transactions)->whereNull('expense_id')->whereNull('income_id')->delete();
            $transactions->update(['bank_connection_id' => null]);

            $connection->delete();
        });
    }
}
