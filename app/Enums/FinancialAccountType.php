<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of a financial account (`financial_accounts.type`).
 * The values are those of Directus.
 */
enum FinancialAccountType: string
{
    use HasLabel;

    case CheckingAccount = 'checking_account';
    case Card = 'card';
    case Deposit = 'deposit';
    case OnlineWallet = 'online_wallet';
    case Other = 'other';
}
