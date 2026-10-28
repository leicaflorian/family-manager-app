<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Support\Occurrence;
use App\Support\Schedule;
use App\Support\ScheduleException;
use Carbon\CarbonImmutable;

/**
 * The recurrence rules of planned expenses and incomes: which due dates fall in
 * a period, with their exceptions applied. Pure calculation, no database.
 * It replaces `recurrence.ts` of the Directus panels.
 */
final class Occurrences
{
    /** The most due dates read from one schedule, so a wrong date can never loop for ever. */
    private const int LIMIT = 5000;

    /**
     * Adds months keeping the day of the date; in short months it is the last day.
     *
     * @param  CarbonImmutable  $date  The starting day.
     * @param  int  $months  The months to add, negative to go back.
     */
    public function addMonths(CarbonImmutable $date, int $months): CarbonImmutable
    {
        return $date->addMonthsNoOverflow($months);
    }

    /**
     * The length of one period in days: 7 per week, 30.44 per month, 365.25 per year, times the interval.
     *
     * @param  Schedule  $schedule  The recurrence.
     */
    public function stepDays(Schedule $schedule): float
    {
        $interval = max(1, $schedule->interval);

        return match ($schedule->unit) {
            IntervalUnit::Week => 7.0 * $interval,
            IntervalUnit::Year => 365.25 * $interval,
            IntervalUnit::Month => 30.44 * $interval,
        };
    }

    /**
     * The occurrences whose real date (after any move) falls between two days, both included, by date.
     *
     * @param  Schedule  $schedule  The recurrence.
     * @param  CarbonImmutable  $from  The first day.
     * @param  CarbonImmutable  $to  The last day.
     * @return list<Occurrence>
     */
    public function between(Schedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $schedule->start instanceof CarbonImmutable) {
            return [];
        }

        $fromDay = $from->toDateString();
        $toDay = $to->toDateString();
        $found = [];

        foreach ($this->originalDates($schedule, $from, $to) as $original) {
            $occurrence = $this->apply($schedule, $original);

            if ($occurrence instanceof Occurrence) {
                $day = $occurrence->date->toDateString();

                if ($day >= $fromDay && $day <= $toDay) {
                    $found[] = $occurrence;
                }
            }
        }

        usort($found, static fn (Occurrence $a, Occurrence $b): int => $a->date <=> $b->date);

        return $found;
    }

    /**
     * The occurrence that a movement of the given date registers: the closest to it, within its window.
     * For a one-off item it is the only occurrence. Null when there is none.
     *
     * @param  Schedule  $schedule  The recurrence.
     * @param  CarbonImmutable  $date  The date of the movement.
     */
    public function at(Schedule $schedule, CarbonImmutable $date): ?Occurrence
    {
        $reach = (int) ceil($this->stepDays($schedule) / 2) + 1;
        $list = $schedule->recurring
            ? $this->between($schedule, $date->subDays($reach), $date->addDays($reach))
            : $this->between($schedule, CarbonImmutable::parse('1900-01-01'), CarbonImmutable::parse('2999-12-31'));

        $inside = array_values(array_filter($list, fn (Occurrence $occurrence): bool => $this->inWindow($schedule, $occurrence, $date)));
        // usort is stable, so on a tie the earlier occurrence wins.
        usort($inside, fn (Occurrence $a, Occurrence $b): int => abs($this->daysBetween($a->date, $date)) <=> abs($this->daysBetween($b->date, $date)));

        return $inside[0] ?? null;
    }

    /**
     * Whether a date falls in the window of an occurrence: less than half a period away
     * (half included before, excluded after). A one-off item takes any date.
     * It does not say whether a due date is paid: it is for `at()` and for suggesting a due date in a form.
     *
     * @param  Schedule  $schedule  The recurrence.
     * @param  Occurrence  $occurrence  The occurrence.
     * @param  CarbonImmutable  $date  The date to check.
     */
    public function inWindow(Schedule $schedule, Occurrence $occurrence, CarbonImmutable $date): bool
    {
        if (! $schedule->recurring) {
            return true;
        }

        $half = $this->stepDays($schedule) / 2;
        $diff = $this->daysBetween($occurrence->date, $date);

        return $diff >= -$half && $diff < $half;
    }

    /**
     * The original dates of the series that can end up between two days. The scan is wider than the
     * period because a date override can move an occurrence into it or out of it.
     *
     * @param  Schedule  $schedule  The recurrence, with a start date.
     * @return list<CarbonImmutable>
     */
    private function originalDates(Schedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $schedule->start?->startOfDay();

        if ($start === null) {
            return [];
        }

        if (! $schedule->recurring) {
            return [$start];
        }

        $margin = (int) ceil($this->stepDays($schedule)) + 62;
        $scanFrom = $from->subDays($margin)->toDateString();
        $scanTo = $to->addDays($margin)->toDateString();
        $end = $schedule->end?->toDateString();
        $dates = [];

        for ($k = 0; $k < self::LIMIT; $k++) {
            $date = $this->nth($schedule, $start, $k);
            $day = $date->toDateString();

            if ($day > $scanTo || ($end !== null && $day > $end)) {
                break;
            }

            if ($day >= $scanFrom) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * The k-th date of the series, always counted from the start so the day does not drift after a short month.
     *
     * @param  Schedule  $schedule  The recurrence.
     * @param  CarbonImmutable  $start  The first date.
     * @param  int  $k  How many periods after the start.
     */
    private function nth(Schedule $schedule, CarbonImmutable $start, int $k): CarbonImmutable
    {
        $interval = max(1, $schedule->interval);

        return match ($schedule->unit) {
            IntervalUnit::Week => $start->addDays(7 * $interval * $k),
            IntervalUnit::Year => $this->addMonths($start, 12 * $interval * $k),
            IntervalUnit::Month => $this->addMonths($start, $interval * $k),
        };
    }

    /**
     * Applies to a date of the series all the exceptions that name it. Skip removes it and wins over the rest.
     *
     * @param  Schedule  $schedule  The recurrence.
     * @param  CarbonImmutable  $original  The original date.
     * @return ?Occurrence The occurrence, or null when it is skipped.
     */
    private function apply(Schedule $schedule, CarbonImmutable $original): ?Occurrence
    {
        $day = $original->toDateString();
        $list = array_values(array_filter(
            $schedule->exceptions,
            static fn (ScheduleException $exception): bool => $exception->occurrenceDate->toDateString() === $day,
        ));

        if (array_any($list, static fn (ScheduleException $e): bool => $e->type === ExceptionType::Skip)) {
            return null;
        }

        $amount = null;
        $moved = null;

        // The first exception of each kind that carries its value wins.
        foreach ($list as $exception) {
            if ($exception->type === ExceptionType::AmountOverride && $exception->amount !== null) {
                $amount ??= $exception->amount;
            }

            if ($exception->type === ExceptionType::DateOverride && $exception->date !== null) {
                $moved ??= $exception->date;
            }
        }

        $notes = array_values(array_filter(
            array_map(static fn (ScheduleException $e): ?string => $e->notes, $list),
            static fn (?string $note): bool => $note !== null && $note !== '',
        ));

        return new Occurrence(
            $moved?->startOfDay() ?? $original,
            $original,
            $amount ?? $schedule->amount,
            $schedule->estimated && $amount === null,
            $amount === null ? null : $schedule->amount,
            $notes === [] ? null : implode(' · ', $notes),
        );
    }

    /**
     * The whole days from one date to another, negative when the second is earlier.
     * Rounded because a change of daylight saving time makes a day 23 or 25 hours.
     *
     * @param  CarbonImmutable  $a  The first date.
     * @param  CarbonImmutable  $b  The second date.
     */
    private function daysBetween(CarbonImmutable $a, CarbonImmutable $b): int
    {
        return (int) round($a->startOfDay()->diffInDays($b->startOfDay(), false));
    }
}
