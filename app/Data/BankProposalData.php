<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * «Maybe it is…» under a lone movement of *From the bank*: the one thing the system proposes the movement is, which
 * the person accepts (*Yes*) or rejects (*No*) with its key.
 */
#[TypeScript]
final class BankProposalData extends Data
{
    /**
     * @param  string  $key  what is proposed: `expense:<id>`, `income:<id>`, `planned_expense:<id>:<yyyy-mm-dd>`, `planned_income:<id>:<yyyy-mm-dd>` (the date the rules give) or `transfer:<id of the account>`
     * @param  string  $type  `movement` (an expense or income already recorded), `deadline` (a due date to pay) or `transfer`
     * @param  string  $title  the title of the expense or income, of the payment of the due date, or the name of the other account
     * @param  string|null  $date  the date of the expense or income, or of the due date (`yyyy-mm-dd`); null for a transfer
     * @param  float|null  $amount  the amount recorded or expected, without sign; null for a transfer
     * @param  bool  $recorded  it is already recorded («already recorded on …»)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $date,
        public readonly ?float $amount,
        public readonly bool $recorded,
    ) {}
}
