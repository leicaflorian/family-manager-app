<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Data\AgendaEntryData;
use App\Services\AgendaCalendar;
use App\Support\Reminders\WeeklySummary;
use Carbon\CarbonImmutable;

/**
 * Builds the weekly summary from the open entries of the Agenda, so the mail and the Agenda tell the same things.
 * It runs inside `CurrentFamily::using()`, with nobody signed in.
 */
final readonly class WeeklySummaries
{
    public function __construct(private AgendaCalendar $calendar) {}

    /**
     * The summary of the week that starts on a Monday: what was due before it and is still open, then the week.
     *
     * @param  CarbonImmutable  $monday  the Monday, in Rome
     */
    public function week(CarbonImmutable $monday): WeeklySummary
    {
        $start = $monday->toDateString();
        $entries = $this->calendar->open($monday, $monday->addDays(6));

        $late = array_values(array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->date < $start));
        $rest = array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->date >= $start);
        $toPay = array_values(array_filter($rest, static fn (AgendaEntryData $entry): bool => $entry->amount !== null));
        $tasks = array_values(array_filter($rest, static fn (AgendaEntryData $entry): bool => $entry->amount === null));

        $counted = [...$late, ...$toPay];

        return new WeeklySummary(
            late: $late,
            toPay: $toPay,
            tasks: $tasks,
            total: round(array_sum(array_map(static fn (AgendaEntryData $entry): float => $entry->amount ?? 0.0, $counted)), 2),
            estimated: array_any($counted, static fn (AgendaEntryData $entry): bool => $entry->estimated),
        );
    }
}
