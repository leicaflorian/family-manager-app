<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\AgendaEntryData;

/**
 * How the lists built on the entries of the Agenda (the Home, the calendar, the weekly mail) pick and count them.
 * The one place that tells the payments and the incomes apart from the deadlines and the tasks: whatever must leave
 * the payments or the incomes out of those lists changes it here. Every list keeps the order it was given.
 */
final class AgendaEntries
{
    /**
     * The payments to make.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     * @return list<AgendaEntryData>
     */
    public static function payments(array $entries): array
    {
        return self::where($entries, static fn (AgendaEntryData $entry): bool => $entry->kind === 'payment');
    }

    /**
     * The incomes on their way.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     * @return list<AgendaEntryData>
     */
    public static function incomes(array $entries): array
    {
        return self::where($entries, static fn (AgendaEntryData $entry): bool => $entry->kind === 'income');
    }

    /**
     * What is to do or to pay: everything but the incomes.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     * @return list<AgendaEntryData>
     */
    public static function withoutIncomes(array $entries): array
    {
        return self::where($entries, static fn (AgendaEntryData $entry): bool => $entry->kind !== 'income');
    }

    /**
     * The entries due before a day: the late ones, seen from that day.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     * @param  string  $day  the day, `yyyy-mm-dd`
     * @return list<AgendaEntryData>
     */
    public static function before(array $entries, string $day): array
    {
        return self::where($entries, static fn (AgendaEntryData $entry): bool => $entry->date < $day);
    }

    /**
     * The entries due on a day or later.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     * @param  string  $day  the day, `yyyy-mm-dd`
     * @return list<AgendaEntryData>
     */
    public static function from(array $entries, string $day): array
    {
        return self::where($entries, static fn (AgendaEntryData $entry): bool => $entry->date >= $day);
    }

    /**
     * The sum of the amounts, rounded to the cent; an entry without an amount counts nothing.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     */
    public static function total(array $entries): float
    {
        return round(array_sum(array_map(static fn (AgendaEntryData $entry): float => $entry->amount ?? 0.0, $entries)), 2);
    }

    /**
     * The entries that pass a test, in their order.
     *
     * @param  array<AgendaEntryData>  $entries  the entries
     * @param  callable(AgendaEntryData): bool  $test  whether an entry stays
     * @return list<AgendaEntryData>
     */
    private static function where(array $entries, callable $test): array
    {
        return array_values(array_filter($entries, $test));
    }
}
