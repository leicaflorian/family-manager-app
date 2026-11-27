<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\Deadline;
use App\Support\Schedule;
use Carbon\CarbonImmutable;

/**
 * Finds which due date of a planned expense or income a bank transaction settles. Pure reading: it writes nothing;
 * `SettleFromBank` does the paying. Which planned item a transaction pays is said by the bank filters
 * (`BankFilters`, `ApplyBankFilter`).
 */
final readonly class BankMatcher
{
    /** The most days a due date can be from the transaction, whatever the period. */
    private const int MAX_REACH = 45;

    public function __construct(private Deadlines $deadlines, private Occurrences $occurrences, private BankDuplicates $duplicates) {}

    /**
     * The due date the transaction settles: the one closest to the day of the transaction within half a period (at most
     * 45 days; a day more than half is out), paid or not; only when none is within reach, the oldest late one before
     * the day, else none. A paid one comes with its payment: the transaction belongs to it and must not pay an older
     * month (spec 6.6). A skipped due date is never taken. A planned item that does not repeat has only one due date, at
     * any distance, and only while it is unpaid.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     */
    public function deadline(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned): ?Deadline
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $schedule = Schedule::of($planned);

        if (! $schedule->recurring) {
            return $this->deadlines->unpaidUntil([$planned], CarbonImmutable::parse('2999-12-31'), CarbonImmutable::parse('1900-01-01'), withWaived: true)[0] ?? null;
        }

        // Half a period, cut to whole days: 15 for a month, 3 for a week
        $reach = $this->reach($schedule);
        $near = array_values(array_filter(
            $this->deadlines->between([$planned], $day->subDays($reach), $day->addDays($reach)),
            fn (Deadline $deadline): bool => abs($this->days($deadline, $day)) <= $reach,
        ));

        // The list is in order of date, so on a tie the earlier due date stays first
        usort($near, fn (Deadline $a, Deadline $b): int => abs($this->days($a, $day)) <=> abs($this->days($b, $day)));

        if ($near !== []) {
            return $near[0];
        }

        return array_find(
            $this->deadlines->unpaidUntil([$planned], $day, $day, withWaived: true),
            static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() < $day->toDateString(),
        );
    }

    /**
     * The due date a person who looks for the planned item by hand would pay, at any distance: the one of `deadline()`
     * when it is within reach and open, or paid by hand with a payment no other bank transaction has (the transaction
     * links to it); otherwise the open due date closest to the day of the transaction, before or after it, at most
     * 12 months back and 24 ahead of that day. An item that does not repeat has one due date: open, or paid by hand
     * with a free payment. A skipped due date is never taken.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    public function deadlineAnywhere(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned): ?Deadline
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $schedule = Schedule::of($planned);

        if (! $schedule->recurring) {
            $only = $this->deadlines->between([$planned], CarbonImmutable::parse('1900-01-01'), CarbonImmutable::parse('2999-12-31'))[0] ?? null;

            return $only instanceof Deadline && $this->usable($only, $transaction) ? $only : null;
        }

        $near = $this->deadline($transaction, $planned);

        // A paid one comes only from within reach; an open one counts as near only within reach, else the closest wins
        if ($near instanceof Deadline && ! $near->isSkipped() && $this->usable($near, $transaction)
            && ($near->isPaid() || abs($this->days($near, $day)) <= $this->reach($schedule))) {
            return $near;
        }

        $open = array_values(array_filter(
            $this->deadlines->unpaidUntil([$planned], $day->addMonthsNoOverflow(24), $day, withWaived: true),
            static fn (Deadline $deadline): bool => ! $deadline->isSkipped(),
        ));

        // In order of date: on a tie the earlier due date stays first
        usort($open, fn (Deadline $a, Deadline $b): int => abs($this->days($a, $day)) <=> abs($this->days($b, $day)));

        return $open[0] ?? null;
    }

    /**
     * Whether a due date can be offered: open, or paid by a payment no other bank transaction has.
     *
     * @param  Deadline  $deadline  The due date.
     * @param  BankTransaction  $transaction  The transaction.
     */
    private function usable(Deadline $deadline, BankTransaction $transaction): bool
    {
        return ! $deadline->isPaid() || ($deadline->payment !== null && $this->duplicates->isFree($deadline->payment, $transaction));
    }

    /**
     * How many days a due date can be from the transaction to count as near: half a period, at most 45.
     *
     * @param  Schedule  $schedule  The recurrence.
     */
    private function reach(Schedule $schedule): int
    {
        return min(self::MAX_REACH, (int) floor($this->occurrences->stepDays($schedule) / 2));
    }

    /**
     * The whole days from the day of the transaction to a due date, negative when it is before.
     *
     * @param  Deadline  $deadline  The due date.
     * @param  CarbonImmutable  $day  The day of the transaction.
     */
    private function days(Deadline $deadline, CarbonImmutable $day): int
    {
        return (int) round($day->startOfDay()->diffInDays($deadline->occurrence->date->startOfDay(), false));
    }
}
