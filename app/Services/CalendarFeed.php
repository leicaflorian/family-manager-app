<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AgendaEntryData;
use App\Enums\ReminderStatus;
use App\Models\Person;
use App\Models\Reminder;
use App\Support\Ics\Calendar;
use App\Support\Ics\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

/**
 * The events of the current family's calendar to subscribe to: every open
 * reminder, even a past one, and the entries of the Agenda from 90 days back
 * for the deadlines and 30 days back for the payments, up to 12 months from
 * today. A deadline with its automatic reminder is not an event of its own: the
 * reminder wins. The texts are in the current language.
 */
final readonly class CalendarFeed
{
    /** How many days back the deadlines of cards and documents go, like *To sort out*. */
    private const int DEADLINES_BACK = 90;

    /** How many days back the unpaid payments go, like the Home. */
    private const int PAYMENTS_BACK = 30;

    /** How many months ahead the entries of the Agenda go. */
    private const int MONTHS_AHEAD = 12;

    public function __construct(private Agenda $agenda) {}

    /**
     * The events, the reminders first and then the entries of the Agenda by date.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<CalendarEvent>
     */
    public function events(CarbonImmutable $today): array
    {
        $entries = $this->agenda->entriesWithoutReminders(
            $today,
            $today->addMonthsNoOverflow(self::MONTHS_AHEAD),
            deadlinesBack: self::DEADLINES_BACK,
            paymentsBack: self::PAYMENTS_BACK,
        );

        return [
            ...array_map($this->reminderEvent(...), $this->openReminders()),
            ...array_map($this->entryEvent(...), $entries),
        ];
    }

    /**
     * The open reminders of the family, by date.
     *
     * @return list<Reminder>
     */
    private function openReminders(): array
    {
        return array_values(Reminder::query()
            ->with('person')
            ->where('status', ReminderStatus::Open)
            ->oldest('due_date')
            ->orderBy('id')
            ->get()
            ->all());
    }

    /**
     * The event of a reminder: its title without the deadline, and in the description the missing expense, the
     * notes, who it is for and where to open it.
     *
     * @param  Reminder  $reminder  an open reminder
     */
    private function reminderEvent(Reminder $reminder): CalendarEvent
    {
        $lines = [
            Calendar::missesExpense($reminder->title) ? __('Expected expense missing.') : null,
            $reminder->notes,
            $reminder->person instanceof Person ? __('For: :name', ['name' => "{$reminder->person->first_name} {$reminder->person->last_name}"]) : null,
            // The Agenda until the reminders have a page of their own
            __('Open: :url', ['url' => route('agenda')]),
        ];

        return new CalendarEvent(
            uid: "reminder-{$reminder->id}@family-manager",
            date: $reminder->due_date,
            summary: Calendar::withoutDeadline($reminder->title),
            description: implode("\n", array_filter($lines, static fn (mixed $line): bool => is_string($line) && $line !== '')),
        );
    }

    /**
     * The event of an entry of the Agenda: the title with the amount of a payment or an income, and in the
     * description what it belongs to and the whole address that opens it.
     *
     * @param  AgendaEntryData  $entry  the entry
     */
    private function entryEvent(AgendaEntryData $entry): CalendarEvent
    {
        $summary = $entry->amount === null
            ? $entry->title
            : $entry->title.' · '.Number::currency($entry->amount, 'EUR', app()->getLocale());

        return new CalendarEvent(
            uid: str_replace(':', '/', $entry->key).'@family-manager',
            date: CarbonImmutable::parse($entry->date, 'Europe/Rome'),
            summary: $summary,
            description: implode("\n", array_filter([$entry->subtitle, $entry->href], static fn (string $line): bool => $line !== '')),
        );
    }
}
