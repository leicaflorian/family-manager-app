<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Models\BankConnection;
use App\Support\Banks\BankVisitor;

/**
 * *Update* asked by a person: reads the banks now, as the person who is looking (a read a person asks is not counted
 * in the daily limit of the banks).
 */
final readonly class RefreshBanks
{
    /**
     * @param  SyncBankConnection  $sync  reads one connection
     */
    public function __construct(private SyncBankConnection $sync) {}

    /**
     * Reads the connections one after the other. A bank that fails does not stop the others: no error leaves the read.
     *
     * @param  iterable<BankConnection>  $connections  the connections of the current family to read
     * @param  BankVisitor  $visitor  who asked
     * @return int how many booked transactions are new
     */
    public function handle(iterable $connections, BankVisitor $visitor): int
    {
        $new = 0;

        foreach ($connections as $connection) {
            $new += $this->sync->handle($connection, $visitor);
        }

        return $new;
    }

    /**
     * What the person is told.
     *
     * @param  int  $new  how many transactions are new
     */
    public static function message(int $new): string
    {
        return trans_choice('{0} No new movements.|{1} :count new movement.|[2,*] :count new movements.', $new);
    }
}
