<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il tipo di un conto (`financial_accounts.type`).
 * I valori sono quelli di Directus.
 */
enum TipoConto: string
{
    use HaEtichetta;

    case CheckingAccount = 'checking_account';
    case Card = 'card';
    case Deposit = 'deposit';
    case OnlineWallet = 'online_wallet';
    case Other = 'other';

    /**
     * Il valore come lo leggono le pagine.
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::CheckingAccount => 'Conto corrente',
            self::Card => 'Carta',
            self::Deposit => 'Deposito',
            self::OnlineWallet => 'Conto online',
            self::Other => 'Altro',
        };
    }
}
