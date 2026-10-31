<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IntervalUnit;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Occurrences;
use Carbon\CarbonImmutable;

/**
 * The recurrence of a planned expense or income, as plain values: what
 * {@see Occurrences} needs, with no database behind it.
 */
final readonly class Schedule
{
    /**
     * @param  ?CarbonImmutable  $start  The first due date; without it there are no occurrences.
     * @param  ?CarbonImmutable  $end  The last day of the series, if it ends.
     * @param  bool  $recurring  False when there is only the first due date.
     * @param  int  $interval  Every how many units; below 1 it counts as 1.
     * @param  IntervalUnit  $unit  Week, month or year.
     * @param  float  $amount  The amount of every due date.
     * @param  bool  $estimated  Whether the amount is an estimate.
     * @param  list<ScheduleException>  $exceptions  The exceptions to single occurrences.
     */
    public function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
        public bool $recurring,
        public int $interval,
        public IntervalUnit $unit,
        public float $amount,
        public bool $estimated,
        public array $exceptions = [],
    ) {}

    /**
     * Reads the schedule from a planned expense or income and its exceptions.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    public static function of(PlannedExpense|PlannedIncome $planned): self
    {
        $exceptions = [];

        foreach ($planned->exceptions as $exception) {
            if ($exception->occurrence_date === null) {
                continue;
            }

            $exceptions[] = new ScheduleException(
                $exception->occurrence_date,
                $exception->type,
                $exception->amount_override === null ? null : (float) $exception->amount_override,
                $exception->date_override,
                $exception->notes,
            );
        }

        return new self(
            $planned->start_date,
            $planned->end_date,
            (bool) $planned->is_recurring,
            max(1, (int) $planned->interval),
            $planned->interval_unit,
            (float) $planned->amount,
            (bool) $planned->is_estimated,
            $exceptions,
        );
    }
}
