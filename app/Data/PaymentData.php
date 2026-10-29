<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Expense;
use App\Models\Income;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The payment (or the income received) that settles a due date.
 */
#[TypeScript]
final class PaymentData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $id  the id of the expense or of the income
     * @param  string  $date  the day it was paid, ISO
     * @param  float  $amount  the amount paid
     * @param  string|null  $account  the name of the account it was paid with
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $date,
        public readonly float $amount,
        public readonly ?string $account,
    ) {}

    /**
     * The payment of an expense or an income.
     *
     * @param  Expense|Income  $movement  the expense or income
     */
    public static function fromMovement(Expense|Income $movement): self
    {
        return new self(
            kind: $movement instanceof Expense ? 'expense' : 'income',
            id: $movement->id,
            date: ($movement->date)?->toDateString() ?? '',
            amount: round((float) $movement->amount, 2),
            account: $movement->financialAccount?->name,
        );
    }
}
