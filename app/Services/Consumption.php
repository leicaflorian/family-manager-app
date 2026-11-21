<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Consumption\ConsumptionBillData;
use App\Data\Consumption\ConsumptionFiguresData;
use App\Data\Consumption\ConsumptionMonthData;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\UtilityConsumption;
use App\Support\Money;
use App\Support\Reading\ConsumptionCheck;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;

/**
 * The months, the figures, the bills and the sentence of the Consumption page, worked out from the consumption rows
 * of one utility. Pure: it reads nothing from the database and saves nothing.
 *
 * The consumption and the amount of a bill are split in equal parts among the days of its period (both ends
 * counted), and each month takes its days. The split is done in whole thousandths of a unit and whole cents, with
 * the rounding carried from month to month, so the months of a bill add up to its total exactly.
 *
 * Whether a bill is paid comes from its document: the rows must arrive with `relations()` eager loaded.
 *
 * @phpstan-type Share array{row: UtilityConsumption, milli: int, cents: int|null, from: int, until: int}
 * @phpstan-type Summary array{milli: int|null, cents: int|null, pricedMilli: int, pricedCents: int, days: int, coverage: string, coveredUntil: int|null, estimated: bool, adjusted: bool, ids: list<int>}
 */
final class Consumption
{
    /** The covered days each 12-month period needs before the two are compared. */
    private const int MIN_COVERED_DAYS = 330;

    /** A bill or a month this much above the average price counts as dearer. */
    private const float DEARER = 1.10;

    /** The consumption of a month may grow this much on the year before and still count as not gone up. */
    private const float STEADY = 1.05;

    /** The consumption of a month this much above the year before is worth saying. */
    private const float HIGHER = 1.15;

    /** The days beyond the usual distance between bills before a bill counts as missing. */
    private const int LATE_DAYS = 30;

    /**
     * The relations to eager load on the rows before handing them over: the document, with whether an expense is
     * linked to it (`expenses_exists`), so `paid` costs no query.
     *
     * @return array<string, Closure(Relation<*, *, *>): mixed>
     */
    public static function relations(): array
    {
        return ['document' => static fn (Relation $query): mixed => $query->withExists('expenses')];
    }

    /**
     * One entry per month from `$from` to `$to`, each with the same month of the year before.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows of one utility
     * @param  CarbonImmutable  $from  the first month (any day of it)
     * @param  CarbonImmutable  $to  the last month (any day of it)
     * @return list<ConsumptionMonthData>
     */
    public function months(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shares = $this->shares($rows);
        $months = [];

        foreach ($this->monthsBetween($from, $to) as $month) {
            $current = $this->summary($shares, $month);
            $previous = $this->summary($shares, $month->subYearNoOverflow());

            $months[] = new ConsumptionMonthData(
                month: $month->format('Y-m'),
                quantity: $this->units($current['milli']),
                amount: $this->euro($current['cents']),
                price: $this->price($current),
                previousQuantity: $this->units($previous['milli']),
                previousAmount: $this->euro($previous['cents']),
                coverage: $current['coverage'],
                coveredUntil: $current['coveredUntil'],
                estimated: $current['estimated'],
                adjusted: $current['adjusted'],
                billIds: $current['ids'],
            );
        }

        return $months;
    }

    /**
     * The four figures of the months from `$from` to `$to`.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows of one utility, with `relations()`
     * @param  CarbonImmutable  $from  the first month (any day of it)
     * @param  CarbonImmutable  $to  the last month (any day of it)
     *
     * @throws LogicException when a row with a document arrives without `relations()`
     */
    public function figures(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): ConsumptionFiguresData
    {
        $shares = $this->shares($rows);
        $current = array_map(fn (CarbonImmutable $month): array => $this->summary($shares, $month), $this->monthsBetween($from, $to));
        $previous = array_map(fn (CarbonImmutable $month): array => $this->summary($shares, $month), $this->monthsBetween($from->subYearNoOverflow(), $to->subYearNoOverflow()));
        $bills = $this->within($rows, $from, $to);

        $dearestMonth = null;
        $dearestPrice = null;

        foreach ($this->monthsBetween($from, $to) as $index => $month) {
            $price = $this->price($current[$index]);

            if ($current[$index]['coverage'] === 'complete' && $price !== null && ($dearestPrice === null || $price > $dearestPrice)) {
                $dearestMonth = $month->format('Y-m');
                $dearestPrice = $price;
            }
        }

        $covered = static fn (array $summaries): int => array_sum(array_column($summaries, 'days'));

        return new ConsumptionFiguresData(
            quantity: (float) $this->units(array_sum(array_map(static fn (array $summary): int => $summary['milli'] ?? 0, $current))),
            previousQuantity: $covered($current) >= self::MIN_COVERED_DAYS && $covered($previous) >= self::MIN_COVERED_DAYS
                ? $this->units(array_sum(array_map(static fn (array $summary): int => $summary['milli'] ?? 0, $previous)))
                : null,
            coveredMonths: count(array_filter($current, static fn (array $summary): bool => $summary['coverage'] !== 'none')),
            amount: (float) $this->euro(array_sum(array_map(static fn (array $summary): int => $summary['cents'] ?? 0, $current))),
            bills: $bills->count(),
            toPay: $bills->reject(fn (UtilityConsumption $row): bool => $this->paid($row))->count(),
            averagePrice: $this->averagePrice($current),
            dearestMonth: $dearestMonth,
            dearestPrice: $dearestPrice,
        );
    }

    /**
     * The bills that touch the months from `$from` to `$to`, from the latest.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows of one utility, with `relations()`
     * @param  CarbonImmutable  $from  the first month (any day of it)
     * @param  CarbonImmutable  $to  the last month (any day of it)
     * @param  (Closure(UtilityConsumption): bool)|null  $canUpdate  whether the person can change a row; none: no
     * @param  (Closure(UtilityConsumption): bool)|null  $canDelete  whether the person can delete a row; none: no
     * @return list<ConsumptionBillData>
     *
     * @throws LogicException when a row with a document arrives without `relations()`
     */
    public function bills(Collection $rows, CarbonImmutable $from, CarbonImmutable $to, ?Closure $canUpdate = null, ?Closure $canDelete = null): array
    {
        $shares = $this->shares($rows);
        $average = $this->averagePrice(array_map(fn (CarbonImmutable $month): array => $this->summary($shares, $month), $this->monthsBetween($from, $to)));

        return array_values($this->within($rows, $from, $to)
            ->sortBy([
                static fn (UtilityConsumption $a, UtilityConsumption $b): int => $b->period_end <=> $a->period_end,
                static fn (UtilityConsumption $a, UtilityConsumption $b): int => $b->period_start <=> $a->period_start,
                static fn (UtilityConsumption $a, UtilityConsumption $b): int => $b->id <=> $a->id,
            ])
            ->map(function (UtilityConsumption $row) use ($average, $canUpdate, $canDelete): ConsumptionBillData {
                $quantity = (float) $row->quantity;
                $amount = $row->amount === null ? null : (float) $row->amount;
                $price = $amount !== null && $quantity > 0 ? $amount / $quantity : null;

                return new ConsumptionBillData(
                    id: $row->id,
                    document_id: $row->document_id,
                    period_start: $row->period_start->toDateString(),
                    period_end: $row->period_end->toDateString(),
                    quantity: $quantity,
                    unit: $row->unit->value,
                    amount: $amount,
                    price: $price === null ? null : round($price, 4),
                    paid: $this->paid($row),
                    is_estimated: $row->is_estimated,
                    has_adjustments: $row->has_adjustments,
                    above_average: $price !== null && $average !== null && $price >= $average * self::DEARER,
                    check: $amount !== null && ConsumptionCheck::priceOutOfRange($row->unit, $amount, $quantity),
                    source: $row->source->value,
                    can_update: $canUpdate instanceof Closure && $canUpdate($row),
                    can_delete: $canDelete instanceof Closure && $canDelete($row),
                );
            })
            ->all());
    }

    /**
     * The one sentence of the page, chosen by rules in order: a dearer price with the same consumption, a higher
     * consumption than the year before, the last two bills estimated, a bill missing; otherwise none.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  all the consumption rows of the utility
     * @param  list<ConsumptionMonthData>  $months  the months shown, from `months()`
     * @param  UtilityType  $type  the type of the utility
     * @param  CarbonImmutable  $today  today
     * @param  string  $locale  the language of the sentence, `en` or `it`
     */
    public function sentence(Collection $rows, array $months, UtilityType $type, CarbonImmutable $today, string $locale): ?string
    {
        $unit = ConsumptionUnit::forUtility($type);

        if (! $unit instanceof ConsumptionUnit || $rows->isEmpty()) {
            return null;
        }

        $complete = collect($months)->last(static fn (ConsumptionMonthData $month): bool => $month->coverage === 'complete' && $month->quantity !== null);

        if ($complete instanceof ConsumptionMonthData && $complete->quantity !== null) {
            $date = CarbonImmutable::parse($complete->month.'-01')->settings(['locale' => $locale]);
            // The same average as the «On average» figure: the priced bills over the months shown
            $shares = $this->shares($rows);
            $average = $this->averagePrice(array_map(
                fn (ConsumptionMonthData $month): array => $this->summary($shares, CarbonImmutable::parse($month->month.'-01')),
                $months,
            ));
            $previous = $complete->previousQuantity;

            if ($complete->price !== null && $average !== null && $complete->price >= $average * self::DEARER && $previous !== null && $complete->quantity <= $previous * self::STEADY) {
                $replace = ['month' => $date->translatedFormat('F'), 'price' => Money::euro($complete->price), 'unit' => $this->unitLabel($unit, $locale), 'average' => Money::euro($average)];

                return match ($type) {
                    UtilityType::Gas => Translated::text('In :month you paid the most for gas this year: :price per :unit against an average of :average. Your consumption did not go up: the price changed.', $replace, $locale),
                    UtilityType::Water => Translated::text('In :month you paid the most for water this year: :price per :unit against an average of :average. Your consumption did not go up: the price changed.', $replace, $locale),
                    default => Translated::text('In :month you paid the most for electricity this year: :price per :unit against an average of :average. Your consumption did not go up: the price changed.', $replace, $locale),
                };
            }

            if ($previous !== null && $previous > 0 && $complete->quantity >= $previous * self::HIGHER) {
                return Translated::text('In :month you used :quantity, :difference more than :previous.', [
                    'month' => $date->translatedFormat('F'),
                    'quantity' => $this->quantity($complete->quantity).' '.$this->unitLabel($unit, $locale),
                    'difference' => $this->quantity($complete->quantity - $previous),
                    'previous' => $date->subYearNoOverflow()->translatedFormat('F Y'),
                ], $locale);
            }
        }

        $sorted = $rows->sortBy([
            static fn (UtilityConsumption $a, UtilityConsumption $b): int => $a->period_end <=> $b->period_end,
            static fn (UtilityConsumption $a, UtilityConsumption $b): int => $a->period_start <=> $b->period_start,
        ])->values();

        if ($sorted->count() >= 2 && $sorted->slice(-2)->every(static fn (UtilityConsumption $row): bool => $row->is_estimated)) {
            return Translated::text('The last two bills are estimated. Send the meter reading to the supplier, so you pay for what you really use.', [], $locale);
        }

        $first = $sorted->first();
        $last = $sorted->last();

        if ($sorted->count() >= 2 && $first instanceof UtilityConsumption && $last instanceof UtilityConsumption) {
            $usual = $this->days($first->period_end, $last->period_end) / ($sorted->count() - 1);

            if ($this->days($last->period_end, $today) > $usual + self::LATE_DAYS) {
                return Translated::text('The bill after :date is missing.', ['date' => $last->period_end->format('d/m/Y')], $locale);
            }
        }

        return null;
    }

    /**
     * The month of the latest day with a bill.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows of one utility
     * @return CarbonImmutable|null the first day of that month, null without rows
     */
    public function lastMonthWithData(Collection $rows): ?CarbonImmutable
    {
        $last = $rows->max(static fn (UtilityConsumption $row): string => $row->period_end->toDateString());

        return is_string($last) ? CarbonImmutable::parse($last)->startOfMonth() : null;
    }

    /**
     * The month of the earliest day with a bill.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows of one utility
     * @return CarbonImmutable|null the first day of that month, null without rows
     */
    public function firstMonthWithData(Collection $rows): ?CarbonImmutable
    {
        $first = $rows->min(static fn (UtilityConsumption $row): string => $row->period_start->toDateString());

        return is_string($first) ? CarbonImmutable::parse($first)->startOfMonth() : null;
    }

    /**
     * Each bill split among the months it touches, by month `YYYY-MM`. The shares of a bill are worked out on the
     * running total of its days, so they add up to the bill exactly.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows
     * @return array<string, list<Share>>
     */
    private function shares(Collection $rows): array
    {
        $shares = [];

        foreach ($rows as $row) {
            $start = $row->period_start->startOfDay();
            $end = $row->period_end->startOfDay();
            $end = $end->lessThan($start) ? $start : $end;
            $days = $this->days($start, $end) + 1;
            $milli = (int) round((float) $row->quantity * 1000);
            $cents = $row->amount === null ? null : Money::cents($row->amount);

            $cursor = $start;
            $counted = 0;
            $milliDone = 0;
            $centsDone = 0;

            while ($cursor->lessThanOrEqualTo($end)) {
                $monthEnd = $cursor->endOfMonth()->startOfDay();
                $until = $monthEnd->lessThan($end) ? $monthEnd : $end;
                $counted += $this->days($cursor, $until) + 1;

                $milliNow = (int) round($milli * $counted / $days);
                $centsNow = $cents === null ? 0 : (int) round($cents * $counted / $days);

                $shares[$cursor->format('Y-m')][] = [
                    'row' => $row,
                    'milli' => $milliNow - $milliDone,
                    'cents' => $cents === null ? null : $centsNow - $centsDone,
                    'from' => $cursor->day,
                    'until' => $until->day,
                ];

                $milliDone = $milliNow;
                $centsDone = $centsNow;
                $cursor = $until->addDay();
            }
        }

        return $shares;
    }

    /**
     * What the bills give one month: totals in thousandths and cents, the covered days and the notes.
     *
     * @param  array<string, list<Share>>  $shares  from `shares()`
     * @param  CarbonImmutable  $month  the month (any day of it)
     * @return Summary
     */
    private function summary(array $shares, CarbonImmutable $month): array
    {
        $entries = $shares[$month->format('Y-m')] ?? [];
        $daysInMonth = $month->daysInMonth;
        $covered = [];
        $milli = 0;
        $cents = null;
        $pricedMilli = 0;
        $pricedCents = 0;
        $ids = [];

        foreach ($entries as $entry) {
            $milli += $entry['milli'];

            if ($entry['cents'] !== null) {
                $cents = ($cents ?? 0) + $entry['cents'];
                $pricedMilli += $entry['milli'];
                $pricedCents += $entry['cents'];
            }

            for ($day = $entry['from']; $day <= $entry['until']; $day++) {
                $covered[$day] = true;
            }

            $ids[] = $entry['row']->id;
        }

        $days = count($covered);

        return [
            'milli' => $entries === [] ? null : $milli,
            'cents' => $cents,
            'pricedMilli' => $pricedMilli,
            'pricedCents' => $pricedCents,
            'days' => $days,
            'coverage' => match (true) {
                $days === 0 => 'none',
                $days >= $daysInMonth => 'complete',
                default => 'partial',
            },
            'coveredUntil' => $days > 0 && ! isset($covered[$daysInMonth]) ? max(array_keys($covered)) : null,
            'estimated' => array_any($entries, static fn (array $entry): bool => $entry['row']->is_estimated),
            'adjusted' => array_any($entries, static fn (array $entry): bool => $entry['row']->has_adjustments),
            'ids' => array_values(array_unique($ids)),
        ];
    }

    /**
     * The first day of each month from `$from` to `$to`.
     *
     * @param  CarbonImmutable  $from  the first month (any day of it)
     * @param  CarbonImmutable  $to  the last month (any day of it)
     * @return list<CarbonImmutable>
     */
    private function monthsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];
        $last = $to->startOfMonth()->startOfDay();

        for ($month = $from->startOfMonth()->startOfDay(); $month->lessThanOrEqualTo($last); $month = $month->addMonthNoOverflow()) {
            $months[] = $month;
        }

        return $months;
    }

    /**
     * The rows whose period touches the months from `$from` to `$to`.
     *
     * @param  Collection<int, UtilityConsumption>  $rows  the consumption rows
     * @param  CarbonImmutable  $from  the first month (any day of it)
     * @param  CarbonImmutable  $to  the last month (any day of it)
     * @return Collection<int, UtilityConsumption>
     */
    private function within(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $first = $from->startOfMonth()->toDateString();
        $last = $to->endOfMonth()->toDateString();

        return $rows->filter(static fn (UtilityConsumption $row): bool => $row->period_start->toDateString() <= $last && $row->period_end->toDateString() >= $first)->values();
    }

    /**
     * Whether a bill is paid: its document is linked to an expense. A row without a document is a past bill
     * written by hand, so it counts as paid.
     *
     * @param  UtilityConsumption  $row  the row, with `relations()` eager loaded
     *
     * @throws LogicException when the row has a document but `relations()` was not loaded
     */
    private function paid(UtilityConsumption $row): bool
    {
        if ($row->document_id === null) {
            return true;
        }

        if (! $row->relationLoaded('document')) {
            throw new LogicException('Load Consumption::relations() on the consumption rows.');
        }

        $document = $row->getRelation('document');

        if (! $document instanceof Document) {
            return true;
        }

        $exists = $document->getAttribute('expenses_exists');

        if ($exists === null) {
            throw new LogicException('Load Consumption::relations() on the consumption rows.');
        }

        return (bool) $exists;
    }

    /**
     * The average price of some months: their euro over their consumption, counting only the bills with an amount.
     *
     * @param  list<Summary>  $summaries  the months
     * @return float|null euro per unit, null without consumption
     */
    private function averagePrice(array $summaries): ?float
    {
        $milli = array_sum(array_column($summaries, 'pricedMilli'));
        $cents = array_sum(array_column($summaries, 'pricedCents'));

        return $milli > 0 ? round(($cents / 100) / ($milli / 1000), 4) : null;
    }

    /**
     * The price of a month, null when its consumption is zero or less or it has no amount.
     *
     * @param  Summary  $summary  the month
     */
    private function price(array $summary): ?float
    {
        if ($summary['cents'] === null || $summary['milli'] === null || $summary['milli'] <= 0 || $summary['pricedMilli'] <= 0) {
            return null;
        }

        return round(($summary['pricedCents'] / 100) / ($summary['pricedMilli'] / 1000), 4);
    }

    /**
     * Thousandths as units.
     *
     * @param  int|null  $milli  thousandths of a unit
     */
    private function units(?int $milli): ?float
    {
        return $milli === null ? null : round($milli / 1000, 3);
    }

    /**
     * Cents as euro.
     *
     * @param  int|null  $cents  cents
     */
    private function euro(?int $cents): ?float
    {
        return $cents === null ? null : round($cents / 100, 2);
    }

    /**
     * The whole days from one date to another, counted on the calendar (a day with the change of time counts as one).
     *
     * @param  CarbonImmutable  $from  the first date
     * @param  CarbonImmutable  $to  the second date
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->startOfDay()->diffInDays($to->startOfDay()));
    }

    /**
     * A consumption as the person reads it in both languages, as `formatQuantity()` writes it on the page: the
     * thousands grouped, at most one decimal (rounded half away from zero, like `Intl.NumberFormat`), none when
     * zero (`1.290,5`, `18,7`).
     *
     * @param  float  $value  the quantity
     */
    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format(round($value, 1), 1, ',', '.'), '0'), ',');
    }

    /**
     * The label of a unit in a language.
     *
     * @param  ConsumptionUnit  $unit  the unit
     * @param  string  $locale  `en` or `it`
     */
    private function unitLabel(ConsumptionUnit $unit, string $locale): string
    {
        return Translated::text('enums.consumption_unit.'.$unit->value, [], $locale);
    }
}
