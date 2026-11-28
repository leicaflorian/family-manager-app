<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\BankTransaction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The commission of a direct debit, as it is shown under the debit in *From the bank*.
 */
#[TypeScript]
final class BankFeeData extends Data
{
    /**
     * @param  int  $id  the transaction of the commission
     * @param  float  $amount  the amount with the sign: negative is a payment
     * @param  string|null  $booked_on  the day the bank booked it (`yyyy-mm-dd`)
     */
    public function __construct(
        public readonly int $id,
        public readonly float $amount,
        public readonly ?string $booked_on,
    ) {}

    /**
     * The data of a commission.
     *
     * @param  BankTransaction  $fee  the transaction of the commission
     */
    public static function fromTransaction(BankTransaction $fee): self
    {
        return new self($fee->id, (float) $fee->amount, $fee->booked_on?->toDateString());
    }
}
