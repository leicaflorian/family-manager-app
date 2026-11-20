<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\FinancialAccountStatus;
use App\Enums\FinancialAccountType;
use App\Models\BankConnection;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\Banks\BankAccount;
use Illuminate\Support\Facades\Auth;

/**
 * Ties the accounts a bank shares to the accounts of the family: the one already tied by the same key, else one not
 * tied yet (or tied to this bank without a key) with the same last four digits, else a new one. Runs inside the family of the connection.
 */
final class LinkBankAccounts
{
    /**
     * @param  BankConnection  $connection  the connection
     * @param  list<BankAccount>  $accounts  the accounts of the session
     * @return list<FinancialAccount> the accounts of the family tied, in the order of the bank
     */
    public function handle(BankConnection $connection, array $accounts): array
    {
        $linked = [];

        foreach ($accounts as $account) {
            $mine = $this->find($connection, $account, $linked) ?? $this->create($connection);

            $mine->forceFill([
                'bank_connection_id' => $connection->id,
                'bank_account_key' => $account->key,
                'bank_account_uid' => $account->uid,
            ]);
            $mine->last_four_digits ??= $account->lastFour;
            $mine->save();
            $linked[] = $mine;
        }

        // An account that was read through this connection and is not shared any more stays, but cannot be read
        $ids = array_map(static fn (FinancialAccount $account): int => $account->id, $linked);
        FinancialAccount::query()
            ->where('bank_connection_id', $connection->id)
            ->whereNotIn('id', $ids)
            ->update(['bank_account_uid' => null]);

        return $linked;
    }

    /**
     * The account of the family an account of the bank is, if there is one.
     *
     * @param  BankConnection  $connection  the connection
     * @param  BankAccount  $account  the account of the bank
     * @param  list<FinancialAccount>  $taken  the accounts already tied in this round
     */
    private function find(BankConnection $connection, BankAccount $account, array $taken): ?FinancialAccount
    {
        $ids = array_map(static fn (FinancialAccount $taken): int => $taken->id, $taken);

        $byKey = FinancialAccount::query()->where('bank_account_key', $account->key)->whereNotIn('id', $ids)->first();

        if ($byKey instanceof FinancialAccount || $account->lastFour === null) {
            return $byKey;
        }

        return FinancialAccount::query()
            ->where('status', FinancialAccountStatus::Active)
            // Free, or already of this bank but without its key (tied before the key was kept): the same account
            ->where(static fn ($query) => $query->whereNull('bank_connection_id')
                ->orWhere(static fn ($tied) => $tied->where('bank_connection_id', $connection->id)->whereNull('bank_account_key')))
            ->where('last_four_digits', $account->lastFour)
            ->whereNotIn('id', $ids)
            ->orderBy('id')
            ->first();
    }

    /**
     * A new account named after the bank, of the bank and of the person who connects, when they are known.
     *
     * @param  BankConnection  $connection  the connection
     */
    private function create(BankConnection $connection): FinancialAccount
    {
        $user = Auth::guard('web')->user();
        $person = $user instanceof User ? Person::query()->where('user_id', $user->id)->first() : null;

        return new FinancialAccount([
            'name' => __('Account :bank', ['bank' => $connection->institution_name]),
            'type' => FinancialAccountType::CheckingAccount,
            'status' => FinancialAccountStatus::Active,
            'organization_id' => Organization::query()->where('name', $connection->institution_name)->value('id'),
            'person_id' => $person?->id,
        ]);
    }
}
