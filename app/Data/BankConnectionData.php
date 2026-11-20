<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankConnectionStatus;
use App\Models\BankConnection;
use App\Models\FinancialAccount;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A bank linked by the family, as *Linked banks* lists it.
 */
#[TypeScript]
final class BankConnectionData extends Data
{
    /**
     * @param  int  $id  the connection
     * @param  string  $institution  the name of the bank
     * @param  BankConnectionStatus  $status  whether it reads, has to be linked again or failed
     * @param  string|null  $valid_until  the last day of the consent (`yyyy-mm-dd`, Rome); null while there is none
     * @param  string|null  $last_synced_at  when the transactions were read last (`yyyy-mm-ddThh:mm`, Rome); null before the first read
     * @param  list<string>  $accounts  the accounts read through it, as «Account name ·· 1234»
     * @param  bool  $syncing  an active connection still waiting for its first read
     */
    public function __construct(
        public readonly int $id,
        public readonly string $institution,
        public readonly BankConnectionStatus $status,
        public readonly ?string $valid_until,
        public readonly ?string $last_synced_at,
        public readonly array $accounts,
        public readonly bool $syncing,
    ) {}

    /**
     * The data of a connection of the current family.
     *
     * @param  BankConnection  $connection  the connection, with its accounts loaded
     */
    public static function fromConnection(BankConnection $connection): self
    {
        return new self(
            $connection->id,
            $connection->institution_name,
            $connection->status,
            $connection->valid_until?->setTimezone('Europe/Rome')->toDateString(),
            $connection->last_synced_at?->setTimezone('Europe/Rome')->format('Y-m-d\TH:i'),
            array_values($connection->accounts
                ->map(static fn (FinancialAccount $account): string => $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits)
                ->all()),
            $connection->status === BankConnectionStatus::Active && $connection->last_synced_at === null,
        );
    }
}
