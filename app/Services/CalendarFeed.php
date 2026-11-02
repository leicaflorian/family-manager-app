<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AgendaEntryData;
use App\Enums\ReminderStatus;
use App\Models\Person;
use App\Models\Reminder;
use App\Services\Reminders\MissingExpenses;
use App\Services\Reminders\ReminderList;
use App\Support\Ics\Calendar;
use App\Support\Ics\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    public function __construct(private Agenda $agenda, private ReminderList $reminders, private MissingExpenses $missing) {}

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

        $reminders = $this->openReminders();
        $missing = $this->missing->of(new EloquentCollection($reminders), $today);

        return [
            ...array_map(fn (Reminder $reminder): CalendarEvent => $this->reminderEvent($reminder, isset($missing[$reminder->id])), $reminders),
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
        $reminders = Reminder::query()
            ->with(['person', 'link'])
            ->where('status', ReminderStatus::Open)
            ->oldest('due_date')
            ->orderBy('id')
            ->get();
        $this->reminders->loadSubjects($reminders);

        return array_values($reminders->all());
    }

    /**
     * The event of a reminder: its title, and in the description the missing expense, the notes, who it is for and
     * where to open it. An automatic reminder is titled with what to do and the thing it is about, as the page does
     * (the date is the day of the event); a title copied from Directus still has its deadline cut off.
     *
     * @param  Reminder  $reminder  an open reminder, with `person` and `link.subject` loaded
     * @param  bool  $missesExpense  whether its card has no planned expense in progress
     */
    private function reminderEvent(Reminder $reminder, bool $missesExpense): CalendarEvent
    {
        $lines = [
            $missesExpense || Calendar::missesExpense($reminder->title) ? __('Expected expense missing.') : null,
            $reminder->notes,
            $reminder->person instanceof Person ? __('For: :name', ['name' => "{$reminder->person->first_name} {$reminder->person->last_name}"]) : null,
            __('Open: :url', ['url' => route('reminders.index')]),
        ];

        return new CalendarEvent(
            uid: "reminder-{$reminder->id}@family-manager",
            date: $reminder->day(),
            summary: $this->reminderTitle($reminder),
            description: implode("\n", array_filter($lines, static fn (mixed $line): bool => is_string($line) && $line !== '')),
        );
    }

    /**
     * The title of a reminder's event: what to do and the thing, or the written title without the old deadline.
     *
     * @param  Reminder  $reminder  the reminder, with `link.subject` loaded
     */
    private function reminderTitle(Reminder $reminder): string
    {
        $title = Calendar::withoutDeadline($this->reminders->titleOf($reminder));

        return $reminder->is_automatic ? ReminderList::joinTitle($title, $this->reminders->subject($reminder->link)?->label) : $title;
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
