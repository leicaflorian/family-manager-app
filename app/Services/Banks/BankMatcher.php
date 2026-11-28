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
    public const int MAX_REACH = 45;

    public function __construct(private Deadlines $deadlines, private Occurrences $occurrences, private BankDuplicates $duplicates) {}

    /**
     * The due date the transaction settles, as `chooseDeadline()` picks it among the due dates of the planned item
     * from 12 months before the day to half a period after it (all of them, for an item that does not repeat).
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     * @param  list<string>  $skipDue  The original dates (`yyyy-mm-dd`) of due dates that earlier movements of a preview already take: only the late-due-date fallback passes over them, as the save does because by then they are paid.
     */
    public function deadline(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned, array $skipDue = []): ?Deadline
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $schedule = Schedule::of($planned);
        $deadlines = $schedule->recurring
            ? $this->deadlines->between([$planned], $day->subMonthsNoOverflow(Deadlines::MONTHS_BACK), $day->addDays($this->reach($schedule)))
            : $this->deadlines->between([$planned], CarbonImmutable::parse('1900-01-01'), CarbonImmutable::parse('2999-12-31'));

        return $this->chooseDeadline($deadlines, $day, $schedule, $skipDue);
    }

    /**
     * Which due date a transaction booked on a day settles, among due dates already read (`BankProposals` reads them
     * once for a whole block): the one closest to the day within half a period (at most 45 days; a day more than half
     * is out), paid or not; only when none is within reach, the oldest late one before the day, at most 12 months back,
     * else none. A paid one comes with its payment: the transaction belongs to it and must not pay an older month
     * (spec 6.6). A planned item that does not repeat has only one due date, at any distance, and only while it is
     * unpaid. Pure: it reads nothing.
     *
     * @param  list<Deadline>  $deadlines  The due dates of the planned item, in order of date, the skipped ones left out; for a recurring item at least those from 12 months before the day to half a period after it.
     * @param  CarbonImmutable  $day  The day the transaction was booked.
     * @param  Schedule  $schedule  The recurrence of the planned item.
     * @param  list<string>  $skipDue  Original dates of due dates to pass over when no due date is within reach.
     */
    public function chooseDeadline(array $deadlines, CarbonImmutable $day, Schedule $schedule, array $skipDue = []): ?Deadline
    {
        if (! $schedule->recurring) {
            return array_find($deadlines, static fn (Deadline $deadline): bool => ! $deadline->isPaid());
        }

        // Half a period, cut to whole days: 15 for a month, 3 for a week
        $reach = $this->reach($schedule);
        $near = array_values(array_filter(
            $deadlines,
            fn (Deadline $deadline): bool => abs($this->days($deadline, $day)) <= $reach,
        ));

        // The list is in order of date, so on a tie the earlier due date stays first
        usort($near, fn (Deadline $a, Deadline $b): int => abs($this->days($a, $day)) <=> abs($this->days($b, $day)));

        if ($near !== []) {
            return $near[0];
        }

        $since = $day->subMonthsNoOverflow(Deadlines::MONTHS_BACK)->toDateString();

        return array_find(
            $deadlines,
            static fn (Deadline $deadline): bool => ! $deadline->isPaid()
                && ! in_array($deadline->occurrence->originalDate->toDateString(), $skipDue, true)
                && $deadline->occurrence->date->toDateString() < $day->toDateString()
                && $deadline->occurrence->date->toDateString() >= $since,
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
