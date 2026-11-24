<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Carbon\CarbonImmutable;

/**
 * One due date of a planned expense or income, with the payment that settles it, if there is one.
 */
final readonly class Deadline
{
    /**
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item the due date belongs to.
     * @param  Occurrence  $occurrence  The due date, with its exceptions applied.
     * @param  Expense|Income|null  $payment  The expense or income that settles it, null while it is open.
     * @param  string  $title  What to call it: the title of the document to pay, else the one of the planned item.
     * @param  FinancialAccount|null  $account  The account to pay with: the one of the planned item, else the default account of the family.
     * @param  bool  $waived  Whether it fell, unpaid, while the Budget was paused: never late nor to pay, but it can still be paid.
     */
    public function __construct(
        public PlannedExpense|PlannedIncome $planned,
        public Occurrence $occurrence,
        public Expense|Income|null $payment,
        public string $title,
        public ?FinancialAccount $account = null,
        public bool $waived = false,
    ) {}

    /**
     * Whether it is a due date of an expense or of an income.
     *
     * @return string `expense` or `income`
     */
    public function kind(): string
    {
        return $this->planned instanceof PlannedExpense ? 'expense' : 'income';
    }

    /**
     * Whether a payment settles it.
     */
    public function isPaid(): bool
    {
        return $this->payment !== null;
    }

    /**
     * Whether an exception skips it: it is not paid and never late.
     */
    public function isSkipped(): bool
    {
        return $this->occurrence->skipped;
    }

    /**
     * Whether it fell, unpaid, while the Budget was paused: the Budget starts again from zero, so it is neither late
     * nor to pay. A payment can still be recorded for it.
     */
    public function isWaived(): bool
    {
        return $this->waived;
    }

    /**
     * Whether it is still to pay: nothing settles it, no exception skips it and the pause of the Budget does not waive it.
     */
    public function isDue(): bool
    {
        return ! $this->isPaid() && ! $this->isSkipped() && ! $this->isWaived();
    }

    /**
     * Whether its day is over and it is still to pay: it is late from the day after, not during the day.
     * A skipped or waived due date is never late.
     *
     * @param  CarbonImmutable  $today  The day (and time) to look from.
     */
    public function isLate(CarbonImmutable $today): bool
    {
        return $this->isDue() && $this->occurrence->date->toDateString() < $today->toDateString();
    }
}
