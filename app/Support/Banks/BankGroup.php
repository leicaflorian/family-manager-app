<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\BankTransaction;
use Illuminate\Support\Collection;

/**
 * Bank transactions still to sort that are of the same thing: the same mandate, counterparty or text, on the same
 * account and in the same direction.
 */
final readonly class BankGroup
{
    /**
     * @param  string  $key  what makes the group, 16 characters (`BankGroups::keyOf()`)
     * @param  string  $name  the counterparty, or the words of the text with an initial capital
     * @param  string  $kind  `expense` or `income`
     * @param  int  $accountId  the account of every transaction
     * @param  Collection<int, BankTransaction>  $transactions  the transactions, newest first
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $kind,
        public int $accountId,
        public Collection $transactions,
    ) {}
}
