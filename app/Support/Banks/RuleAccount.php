<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\FinancialAccount;

/**
 * Which transactions the rule *How it appears at the bank* of a planned item looks at: those of its own account when
 * that account is linked to a bank, those of every account of the family when it is not (or the item has none), so a
 * planned item on the default account still recognises the debits of the account the bank reads. Every transaction
 * comes from a linked account; the rows a bank leaves behind when it is disconnected are only declined, put aside or
 * already linked, and a rule never settles those. The matcher, the paying after a save and the preview all ask here.
 */
final class RuleAccount
{
    /**
     * The one account a rule of a planned item on this account reads, or null when it reads every account.
     *
     * @param  int|string|null  $accountId  the account of the planned item; a model just created from a form still
     *                                      holds it as the text the form sent
     */
    public static function of(int|string|null $accountId): ?int
    {
        if (! is_numeric($accountId)) {
            return null;
        }

        $id = (int) $accountId;

        return FinancialAccount::query()->whereKey($id)->whereNotNull('bank_connection_id')->exists() ? $id : null;
    }

    /**
     * Whether a rule of a planned item on an account looks at the transactions of another account.
     *
     * @param  int|string|null  $plannedAccountId  the account of the planned item
     * @param  int|string  $transactionAccountId  the account of the transaction
     */
    public static function reads(int|string|null $plannedAccountId, int|string $transactionAccountId): bool
    {
        $account = self::of($plannedAccountId);

        return $account === null || $account === (int) $transactionAccountId;
    }
}
