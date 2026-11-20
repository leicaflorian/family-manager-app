<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A planned expense or income a bank transaction can be assigned to (*It belongs to a planned expense*): its name,
 * who it is paid to and the due date the transaction would settle.
 */
#[TypeScript]
final class BankPlannedChoiceData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $id  the planned item
     * @param  string  $title  its name
     * @param  string|null  $organization  who it is paid to or comes from
     * @param  string  $due_on  the due date that would be paid (`yyyy-mm-dd`)
     * @param  bool  $late  the due date is already over
     * @param  float  $amount  what it is planned for, commission included
     * @param  bool  $estimated  the amount is an estimate
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $organization,
        public readonly string $due_on,
        public readonly bool $late,
        public readonly float $amount,
        public readonly bool $estimated,
    ) {}

    /**
     * The choice of a planned item and the due date it offers. It reads the organization, so the caller loads it.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  Deadline  $deadline  the open due date a transaction would settle
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function fromDeadline(PlannedExpense|PlannedIncome $planned, Deadline $deadline, CarbonImmutable $today): self
    {
        $fee = $planned instanceof PlannedExpense ? (float) $planned->fee_amount : 0.0;

        return new self(
            kind: $planned instanceof PlannedExpense ? 'expense' : 'income',
            id: $planned->id,
            title: $planned->displayTitle(),
            organization: $planned->organization?->name,
            due_on: $deadline->occurrence->date->toDateString(),
            late: $deadline->isLate($today),
            amount: round((float) $planned->amount + $fee, 2),
            estimated: (bool) $planned->is_estimated,
        );
    }
}
