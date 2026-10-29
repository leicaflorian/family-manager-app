<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Services\Occurrences;
use App\Support\Occurrence;
use App\Support\Schedule;
use App\Support\ScheduleException;
use Carbon\CarbonImmutable;

/**
 * Builds a schedule; the defaults are a monthly expense of 100 from 31 January 2026.
 *
 * @param  list<ScheduleException>  $exceptions
 */
function schedule(
    ?string $start = '2026-01-31',
    ?string $end = null,
    bool $recurring = true,
    int $interval = 1,
    IntervalUnit $unit = IntervalUnit::Month,
    float $amount = 100,
    bool $estimated = false,
    array $exceptions = [],
): Schedule {
    return new Schedule(
        $start === null ? null : CarbonImmutable::parse($start),
        $end === null ? null : CarbonImmutable::parse($end),
        $recurring,
        $interval,
        $unit,
        $amount,
        $estimated,
        $exceptions,
    );
}

/** Builds an exception of the occurrence of `$on`. */
function scheduleException(
    string $on,
    ExceptionType $type,
    ?float $amount = null,
    ?string $date = null,
    ?string $notes = null,
): ScheduleException {
    return new ScheduleException(
        CarbonImmutable::parse($on),
        $type,
        $amount,
        $date === null ? null : CarbonImmutable::parse($date),
        $notes,
    );
}

/**
 * The dates of the occurrences between two days, as `Y-m-d`.
 *
 * @return list<string>
 */
function occurrenceDates(Schedule $schedule, string $from, string $to): array
{
    return array_map(
        static fn (Occurrence $occurrence): string => $occurrence->date->toDateString(),
        (new Occurrences)->between($schedule, CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
    );
}

/** Adds months to a `Y-m-d` day and gives back a `Y-m-d` day. */
function plusMonths(string $date, int $months): string
{
    return (new Occurrences)->addMonths(CarbonImmutable::parse($date), $months)->toDateString();
}

/** The occurrence a movement of `$date` registers. */
function occurrenceAt(Schedule $schedule, string $date): ?Occurrence
{
    return (new Occurrences)->at($schedule, CarbonImmutable::parse($date));
}

/** Whether a movement of `$date` falls in the window of an occurrence of `$schedule`. */
function inWindow(Schedule $schedule, string $occurrenceDate, string $date): bool
{
    $day = CarbonImmutable::parse($occurrenceDate);

    return (new Occurrences)->inWindow($schedule, new Occurrence($day, $day, 100, false, null, null), CarbonImmutable::parse($date));
}

describe('addMonths', function (): void {
    it('31 January plus one month is the last day of February', function (): void {
        expect(plusMonths('2026-01-31', 1))->toBe('2026-02-28');
    });

    it('31 January plus one month in a leap year is 29 February', function (): void {
        expect(plusMonths('2024-01-31', 1))->toBe('2024-02-29');
    });

    it('30 November plus two months crosses the year', function (): void {
        expect(plusMonths('2026-11-30', 2))->toBe('2027-01-30');
    });

    it('negative months go back across the year', function (): void {
        expect(plusMonths('2026-03-15', -3))->toBe('2025-12-15')
            ->and(plusMonths('2026-01-15', -1))->toBe('2025-12-15');
    });

    it('twelve months keep the day, and 29 February falls on 28 in a common year', function (): void {
        expect(plusMonths('2026-05-10', 12))->toBe('2027-05-10')
            ->and(plusMonths('2024-02-29', 12))->toBe('2025-02-28');
    });

    it('zero months returns the same date', function (): void {
        expect(plusMonths('2026-06-17', 0))->toBe('2026-06-17');
    });
});

describe('stepDays', function (): void {
    it('is 7 days per week, 365.25 per year and 30.44 per month', function (): void {
        $occurrences = new Occurrences;
        expect($occurrences->stepDays(schedule(interval: 1, unit: IntervalUnit::Week)))->toBe(7.0)
            ->and($occurrences->stepDays(schedule(interval: 2, unit: IntervalUnit::Week)))->toBe(14.0)
            ->and($occurrences->stepDays(schedule(interval: 1, unit: IntervalUnit::Year)))->toBe(365.25)
            ->and($occurrences->stepDays(schedule(interval: 1, unit: IntervalUnit::Month)))->toBe(30.44)
            ->and($occurrences->stepDays(schedule(interval: 3, unit: IntervalUnit::Month)))->toEqualWithDelta(91.32, 1e-10);
    });

    it('a zero interval counts as 1', function (): void {
        expect((new Occurrences)->stepDays(schedule(interval: 0, unit: IntervalUnit::Week)))->toBe(7.0);
    });
});

describe('between', function (): void {
    it('a monthly expense from 31 January keeps the end of the month', function (): void {
        $list = (new Occurrences)->between(schedule(), CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-04-30'));
        expect(array_map(static fn (Occurrence $o): string => $o->date->toDateString(), $list))
            ->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'])
            ->and(array_map(static fn (Occurrence $o): string => $o->originalDate->toDateString(), $list))
            ->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'])
            ->and(array_all($list, static fn (Occurrence $o): bool => $o->amount === 100.0 && ! $o->estimated))->toBeTrue();
    });

    it('a range before the start is empty', function (): void {
        expect(occurrenceDates(schedule(), '2025-01-01', '2025-12-31'))->toBe([]);
    });

    it('no start date gives nothing', function (): void {
        expect(occurrenceDates(schedule(start: null), '2026-01-01', '2026-12-31'))->toBe([]);
    });

    it('the end date stops the series', function (): void {
        expect(occurrenceDates(schedule(end: '2026-03-15'), '2026-01-01', '2026-04-30'))->toBe(['2026-01-31', '2026-02-28']);
    });

    it('an occurrence on the end date is kept', function (): void {
        expect(occurrenceDates(schedule(end: '2026-03-31'), '2026-01-01', '2026-04-30'))
            ->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
    });

    it('the range limits are included', function (): void {
        expect(occurrenceDates(schedule(), '2026-02-28', '2026-03-31'))->toBe(['2026-02-28', '2026-03-31']);
    });

    it('weekly, every week and every two weeks', function (): void {
        $weekly = schedule(start: '2026-01-05', interval: 1, unit: IntervalUnit::Week);
        expect(occurrenceDates($weekly, '2026-01-01', '2026-01-31'))
            ->toBe(['2026-01-05', '2026-01-12', '2026-01-19', '2026-01-26']);
        $biweekly = schedule(start: '2026-01-05', interval: 2, unit: IntervalUnit::Week);
        expect(occurrenceDates($biweekly, '2026-01-01', '2026-02-28'))
            ->toBe(['2026-01-05', '2026-01-19', '2026-02-02', '2026-02-16']);
    });

    it('every three months', function (): void {
        expect(occurrenceDates(schedule(start: '2026-01-15', interval: 3), '2026-01-01', '2026-12-31'))
            ->toBe(['2026-01-15', '2026-04-15', '2026-07-15', '2026-10-15']);
    });

    it('yearly from 29 February falls on 28 February in common years', function (): void {
        expect(occurrenceDates(schedule(start: '2024-02-29', unit: IntervalUnit::Year), '2024-01-01', '2028-12-31'))
            ->toBe(['2024-02-29', '2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29']);
    });

    it('a zero interval means every month', function (): void {
        expect(occurrenceDates(schedule(start: '2026-01-10', interval: 0), '2026-01-01', '2026-03-31'))
            ->toBe(['2026-01-10', '2026-02-10', '2026-03-10']);
    });

    it('a one-off expense gives only its start date, when inside the range', function (): void {
        $once = schedule(start: '2026-05-20', recurring: false);
        expect(occurrenceDates($once, '2026-01-01', '2026-12-31'))->toBe(['2026-05-20'])
            ->and(occurrenceDates($once, '2026-06-01', '2026-12-31'))->toBe([]);
    });

    it('the amount and the estimated flag are passed on', function (): void {
        $first = (new Occurrences)->between(schedule(amount: 12.5, estimated: true), CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-31'))[0];
        expect($first->date->toDateString())->toBe('2026-01-31')
            ->and($first->originalDate->toDateString())->toBe('2026-01-31')
            ->and($first->amount)->toBe(12.5)
            ->and($first->estimated)->toBeTrue()
            ->and($first->baseAmount)->toBeNull()
            ->and($first->notes)->toBeNull();
    });

    it('a skip exception removes that occurrence', function (): void {
        $schedule = schedule(exceptions: [scheduleException('2026-02-28', ExceptionType::Skip)]);
        expect(occurrenceDates($schedule, '2026-01-01', '2026-03-31'))->toBe(['2026-01-31', '2026-03-31']);
    });

    it('a skipped occurrence comes back flagged when asked for, on its own day and amount', function (): void {
        $schedule = schedule(exceptions: [
            scheduleException('2026-02-28', ExceptionType::Skip, notes: 'Vacanze'),
            scheduleException('2026-02-28', ExceptionType::AmountOverride, amount: 75.5),
        ]);
        $list = (new Occurrences)->between($schedule, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'), withSkipped: true);

        expect(array_map(static fn (Occurrence $o): bool => $o->skipped, $list))->toBe([false, true, false])
            ->and($list[1]->date->toDateString())->toBe('2026-02-28')
            ->and($list[1]->amount)->toBe(100.0)
            ->and($list[1]->baseAmount)->toBeNull()
            ->and($list[1]->notes)->toBe('Vacanze');
    });

    it('an amount override changes only that occurrence', function (): void {
        $schedule = schedule(exceptions: [scheduleException('2026-02-28', ExceptionType::AmountOverride, amount: 75.5)]);
        $list = (new Occurrences)->between($schedule, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'));
        expect(array_map(static fn (Occurrence $o): float => $o->amount, $list))->toBe([100.0, 75.5, 100.0]);
    });

    it('a date override moves the occurrence and keeps its original date', function (): void {
        $schedule = schedule(exceptions: [scheduleException('2026-02-28', ExceptionType::DateOverride, date: '2026-03-02')]);
        $list = (new Occurrences)->between($schedule, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'));
        expect(array_map(static fn (Occurrence $o): array => [$o->date->toDateString(), $o->originalDate->toDateString()], $list))->toBe([
            ['2026-01-31', '2026-01-31'],
            ['2026-03-02', '2026-02-28'],
            ['2026-03-31', '2026-03-31'],
        ]);
    });

    it('a date override can bring an occurrence into the range from outside, or push it out', function (): void {
        $moved = schedule(exceptions: [scheduleException('2026-02-28', ExceptionType::DateOverride, date: '2026-03-02')]);
        expect(occurrenceDates($moved, '2026-03-01', '2026-03-10'))->toBe(['2026-03-02'])
            ->and(occurrenceDates($moved, '2026-02-01', '2026-02-28'))->toBe([]);
    });

    it('is no longer estimated when an exception changes the amount', function (): void {
        $schedule = schedule(start: '2026-01-10', amount: 72, estimated: true, exceptions: [
            scheduleException('2026-02-10', ExceptionType::AmountOverride, amount: 80),
            scheduleException('2026-03-10', ExceptionType::DateOverride, date: '2026-03-12'),
        ]);
        $list = (new Occurrences)->between($schedule, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'));
        expect(array_map(static fn (Occurrence $o): bool => $o->estimated, $list))->toBe([true, false, true]);
    });

    it('keeps the base amount and the notes of the exceptions', function (): void {
        $list = (new Occurrences)->between(retta(), CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31'));
        expect($list)->toHaveCount(1)
            ->and($list[0]->date->toDateString())->toBe('2026-12-05')
            ->and($list[0]->amount)->toBe(60.0)
            ->and($list[0]->baseAmount)->toBe(45.0)
            ->and($list[0]->notes)->toBe('Comprende la quota del saggio di Natale');
    });

    it('joins the notes of the exceptions of one date with a dot', function (): void {
        $schedule = schedule(start: '2026-01-10', exceptions: [
            scheduleException('2026-02-10', ExceptionType::AmountOverride, amount: 90, notes: 'More'),
            scheduleException('2026-02-10', ExceptionType::DateOverride, date: '2026-02-12', notes: 'Later'),
            scheduleException('2026-02-10', ExceptionType::DateOverride, date: '2026-02-12'),
        ]);
        $list = (new Occurrences)->between($schedule, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-28'));
        expect($list[0]->notes)->toBe('More · Later');
    });

    it('lists the dance fee of the example: June, then September to December, December at 60.00', function (): void {
        $list = (new Occurrences)->between(retta(), CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-12-31'));
        expect(array_map(static fn (Occurrence $o): string => $o->date->toDateString(), $list))
            ->toBe(['2026-06-05', '2026-09-05', '2026-10-05', '2026-11-05', '2026-12-05'])
            ->and(array_map(static fn (Occurrence $o): float => $o->amount, $list))->toBe([45.0, 45.0, 45.0, 45.0, 60.0]);
    });
});

/** The dance fee of the analysis: 45.00 every month on the 5th from 05/09/2025, two holidays and a December at 60.00. */
function retta(): Schedule
{
    return schedule(start: '2025-09-05', amount: 45, exceptions: [
        scheduleException('2026-07-05', ExceptionType::Skip, notes: 'Vacanze'),
        scheduleException('2026-08-05', ExceptionType::Skip, notes: 'Vacanze'),
        scheduleException('2026-12-05', ExceptionType::AmountOverride, amount: 60, notes: 'Comprende la quota del saggio di Natale'),
    ]);
}

describe('several exceptions on the same occurrence', function (): void {
    /** @return list<ScheduleException> */
    function exceptionsOf(string ...$kinds): array
    {
        $all = [
            'amount' => scheduleException('2026-11-10', ExceptionType::AmountOverride, amount: 151.44),
            'date' => scheduleException('2026-11-10', ExceptionType::DateOverride, date: '2026-11-13'),
            'skip' => scheduleException('2026-11-10', ExceptionType::Skip),
        ];

        return array_map(static fn (string $kind): ScheduleException => $all[$kind], array_values($kinds));
    }

    it('applies an amount and a date exception on the same occurrence', function (): void {
        foreach ([['amount', 'date'], ['date', 'amount']] as $order) {
            $list = (new Occurrences)->between(
                schedule(start: '2026-01-10', amount: 120, exceptions: exceptionsOf(...$order)),
                CarbonImmutable::parse('2026-11-01'),
                CarbonImmutable::parse('2026-11-30'),
            );
            expect($list)->toHaveCount(1)
                ->and($list[0]->date->toDateString())->toBe('2026-11-13')
                ->and($list[0]->originalDate->toDateString())->toBe('2026-11-10')
                ->and($list[0]->amount)->toBe(151.44)
                ->and($list[0]->estimated)->toBeFalse();
        }
    });

    it('skip wins over the other exceptions of the same date', function (): void {
        foreach ([['amount', 'date', 'skip'], ['skip', 'amount']] as $order) {
            expect(occurrenceDates(schedule(start: '2026-01-10', amount: 120, exceptions: exceptionsOf(...$order)), '2026-11-01', '2026-11-30'))->toBe([]);
        }
    });
});

describe('at', function (): void {
    it('gives the occurrence a movement of that date registers, by its original date', function (): void {
        expect(occurrenceAt(schedule(start: '2026-01-10', interval: 2), '2026-11-13')?->originalDate->toDateString())->toBe('2026-11-10');
    });

    it('a one-off expense gives its only occurrence, whatever the date', function (): void {
        expect(occurrenceAt(schedule(start: '2026-11-30', recurring: false), '2026-10-01')?->date->toDateString())->toBe('2026-11-30');
    });

    it('gives null when the commitment has ended', function (): void {
        expect(occurrenceAt(schedule(start: '2026-01-10', end: '2026-06-30'), '2026-11-13'))->toBeNull();
    });
});

describe('inWindow', function (): void {
    it('a movement on the same date is registered', function (): void {
        expect(inWindow(schedule(), '2026-03-31', '2026-03-31'))->toBeTrue();
    });

    it('a movement a few days away is registered', function (): void {
        expect(inWindow(schedule(), '2026-03-31', '2026-04-03'))->toBeTrue()
            ->and(inWindow(schedule(), '2026-03-31', '2026-03-28'))->toBeTrue();
    });

    it('a movement far away is not', function (): void {
        expect(inWindow(schedule(), '2026-03-31', '2026-05-15'))->toBeFalse()
            ->and(inWindow(schedule(), '2026-03-31', '2026-02-01'))->toBeFalse();
    });

    it('monthly window is half of 30.44 days: 15 days away yes, 16 no', function (): void {
        expect(inWindow(schedule(), '2026-03-31', '2026-04-15'))->toBeTrue()
            ->and(inWindow(schedule(), '2026-03-31', '2026-04-16'))->toBeFalse()
            ->and(inWindow(schedule(), '2026-03-31', '2026-03-16'))->toBeTrue()
            ->and(inWindow(schedule(), '2026-03-31', '2026-03-15'))->toBeFalse();
    });

    it('weekly window is 3.5 days: 3 days yes, 4 no, either side', function (): void {
        $weekly = schedule(interval: 1, unit: IntervalUnit::Week);
        expect(inWindow($weekly, '2026-03-31', '2026-04-03'))->toBeTrue()
            ->and(inWindow($weekly, '2026-03-31', '2026-04-04'))->toBeFalse()
            ->and(inWindow($weekly, '2026-03-31', '2026-03-28'))->toBeTrue()
            ->and(inWindow($weekly, '2026-03-31', '2026-03-27'))->toBeFalse();
    });

    it('a one-off expense is registered by a movement of any date', function (): void {
        expect(inWindow(schedule(recurring: false), '2026-03-31', '2020-01-01'))->toBeTrue();
    });
});
