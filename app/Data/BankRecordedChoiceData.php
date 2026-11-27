<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Expense;
use App\Models\Income;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An expense or income somebody already recorded that a bank transaction can be linked to (*It belongs to an
 * expense*): its name, who it is paid to, its day and amount.
 */
#[TypeScript]
final class BankRecordedChoiceData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $id  the expense or income
     * @param  string  $title  its name
     * @param  string|null  $organization  who it was paid to or came from
     * @param  string  $date  the day it was recorded for (`yyyy-mm-dd`)
     * @param  float  $amount  what it is, always positive
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $organization,
        public readonly string $date,
        public readonly float $amount,
    ) {}

    /**
     * The choice of a recorded movement. It reads the organization, so the caller loads it.
     *
     * @param  Expense|Income  $movement  the expense or income
     */
    public static function fromMovement(Expense|Income $movement): self
    {
        return new self(
            kind: $movement instanceof Expense ? 'expense' : 'income',
            id: $movement->id,
            title: $movement instanceof Expense ? $movement->title : $movement->label(),
            organization: $movement->organization?->name,
            date: $movement->date?->toDateString() ?? '',
            amount: round(abs((float) $movement->amount), 2),
        );
    }
}
