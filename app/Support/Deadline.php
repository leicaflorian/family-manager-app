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
     */
    public function __construct(
        public PlannedExpense|PlannedIncome $planned,
        public Occurrence $occurrence,
        public Expense|Income|null $payment,
        public string $title,
        public ?FinancialAccount $account = null,
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
     * Whether its day is over and nothing settles it: it is late from the day after, not during the day.
     * A skipped due date is never late.
     *
     * @param  CarbonImmutable  $today  The day (and time) to look from.
     */
    public function isLate(CarbonImmutable $today): bool
    {
        return ! $this->isPaid() && ! $this->isSkipped() && $this->occurrence->date->toDateString() < $today->toDateString();
    }
}
