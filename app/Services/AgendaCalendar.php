<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AgendaCalendarData;
use App\Data\AgendaEntryData;
use App\Enums\AgendaReminderState;
use App\Support\AgendaEntries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The data of the Agenda calendar: the entries of the list and the done ones that fall in the weeks of a month.
 */
final readonly class AgendaCalendar
{
    /** How many days back the deadlines and the payments of the list go. */
    public const int DAYS_BACK = 90;

    public function __construct(private Agenda $agenda, private AgendaTasks $tasks) {}

    /**
     * The things to do from the late ones to the end of the period, in order of day and title. Incomes are not things
     * to do, and a deadline whose automatic reminder is done belongs to the done view; late deadlines and unpaid
     * payments stay for 90 days, tasks without limit. The Home and the calendar keep the done deadlines, so the filter
     * stays here and not in `Agenda`.
     *
     * @param  CarbonImmutable  $today  today, in Rome
     * @param  CarbonImmutable  $until  the last day of the period
     * @param  int  $back  how many days back the deadlines and the payments go
     * @return list<AgendaEntryData>
     */
    public function open(CarbonImmutable $today, CarbonImmutable $until, int $back = self::DAYS_BACK): array
    {
        return $this->sorted([
            ...array_filter(AgendaEntries::withoutIncomes($this->agenda->entries($today, $until, $back, $back)), static fn (AgendaEntryData $entry): bool => $entry->kind !== 'deadline' || $entry->reminder?->state !== AgendaReminderState::Done),
            ...$this->tasks->open($today, $until),
        ]);
    }

    /**
     * The month of the calendar: its weeks, from the Monday before the first day to the Sunday after the last. What is
     * still to do is what the list shows, kept when it falls in the weeks; a past month asks the list for the days
     * back to its first week, so what is late and still open shows where it was due. What is done is the payments made
     * and the reminders done of those weeks, a done reminder that a payment of its card carries on the same day once.
     *
     * @param  CarbonImmutable  $today  today, in Rome
     * @param  CarbonImmutable  $month  the first day of the month
     */
    public function month(CarbonImmutable $today, CarbonImmutable $month): AgendaCalendarData
    {
        // Monday and Sunday by name: the locale must not move the weeks against the grid of the page
        $from = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $to = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        $paid = $this->agenda->paidBetween($from, $to);
        $back = max(self::DAYS_BACK, (int) $from->diffInDays($today->startOfDay(), false));
        $inside = static fn (AgendaEntryData $entry): bool => $entry->date >= $from->toDateString() && $entry->date <= $to->toDateString();

        return new AgendaCalendarData(
            month: $month->format('Y-m'),
            from: $from->toDateString(),
            to: $to->toDateString(),
            today: $today->toDateString(),
            entries: array_values(array_filter($this->open($today, $to, $back), $inside)),
            done: $this->sorted([...$paid['entries'], ...$this->tasks->doneBetween($from, $to, $today, $paid['reminders'])]),
        );
    }

    /**
     * The entries by day and then by title.
     *
     * @param  array<int, AgendaEntryData>  $entries  the entries
     * @return list<AgendaEntryData>
     */
    private function sorted(array $entries): array
    {
        $entries = array_values($entries);
        usort($entries, static fn (AgendaEntryData $a, AgendaEntryData $b): int => [$a->date, mb_strtolower($a->title)] <=> [$b->date, mb_strtolower($b->title)]);

        return $entries;
    }
}
