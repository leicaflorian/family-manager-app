<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaReminderData;
use App\Data\ReminderSubjectData;
use App\Enums\ReminderStatus;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Services\Reminders\ReminderList;
use Carbon\CarbonImmutable;

/**
 * The tasks of the Agenda: the reminders written by hand that are still to do, as rows next to the deadlines and
 * the payments. An automatic reminder is not a task: it belongs to the deadline it comes from.
 */
final readonly class AgendaTasks
{
    public function __construct(private ReminderList $list, private Agenda $agenda) {}

    /**
     * The open tasks up to a day, late ones included without a limit back, by day and then by id. The day that
     * counts is the one a task was postponed to.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  CarbonImmutable  $until  the last day, included
     * @return list<AgendaEntryData>
     */
    public function open(CarbonImmutable $today, CarbonImmutable $until): array
    {
        $reminders = Reminder::query()
            ->where('is_automatic', false)
            ->open()
            ->whereRaw('coalesce(postponed_to, due_date) <= ?', [$until->toDateString()])
            ->with(['link', 'person'])
            ->orderByRaw('coalesce(postponed_to, due_date)')
            ->orderBy('id')
            ->get();

        $this->list->loadSubjects($reminders);
        $groups = ReminderGroup::query()->get();
        $accounts = $this->agenda->accounts();

        return array_values($reminders->map(function (Reminder $reminder) use ($today, $groups, $accounts): AgendaEntryData {
            $subject = $this->list->subject($reminder->link);

            return $this->entry($reminder, $subject, $this->agenda->reminderOf($reminder, $today, $groups, $accounts));
        })->all());
    }

    /**
     * The done reminders, the most recent first, one page of them. A task stays a `task`; an automatic reminder
     * becomes the `deadline` it came from, with the card or the document as its link.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  int  $page  the page, from 1
     * @param  int|null  $personId  only the reminders of this person; null for everybody
     * @param  int  $perPage  how many rows a page has
     * @return array{entries: list<AgendaEntryData>, next: int|null}
     */
    public function done(CarbonImmutable $today, int $page, ?int $personId, int $perPage = 20): array
    {
        // One more than the page tells whether there is a next one
        $reminders = Reminder::query()
            ->where('status', ReminderStatus::Done)
            ->when($personId !== null, static fn ($query) => $query->where('person_id', $personId))
            ->with(['link', 'person'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $next = $reminders->count() > $perPage ? $page + 1 : null;
        $reminders = $reminders->take($perPage)->values();

        $this->list->loadSubjects($reminders);
        $groups = ReminderGroup::query()->get();
        $accounts = $this->agenda->accounts();

        return [
            'entries' => array_values($reminders->map(function (Reminder $reminder) use ($today, $groups, $accounts): AgendaEntryData {
                $subject = $this->list->subject($reminder->link);
                $data = $this->agenda->reminderOf($reminder, $today, $groups, $accounts);

                return $reminder->is_automatic ? $this->deadlineEntry($reminder, $subject, $data) : $this->entry($reminder, $subject, $data, true);
            })->all()),
            'next' => $next,
        ];
    }

    /**
     * The entry of a done automatic reminder, as the deadline it came from.
     *
     * @param  Reminder  $reminder  the reminder, with `person` loaded
     * @param  ReminderSubjectData|null  $subject  the card or the document it is linked to
     * @param  AgendaReminderData  $data  its reminder as the Agenda shows it
     */
    private function deadlineEntry(Reminder $reminder, ?ReminderSubjectData $subject, AgendaReminderData $data): AgendaEntryData
    {
        $person = $reminder->person instanceof Person ? $reminder->person : null;
        $title = $this->list->titleOf($reminder);

        return new AgendaEntryData(
            key: 'reminder:'.$reminder->id,
            kind: 'deadline',
            date: $reminder->due_date->toDateString(),
            title: $title,
            subtitle: $subject instanceof ReminderSubjectData ? $subject->label : '',
            name: $subject instanceof ReminderSubjectData ? $subject->label : $title,
            detail: $subject?->type === 'document' ? __('Document expiry') : $title,
            area: $subject instanceof ReminderSubjectData ? $subject->area : AgendaData::GENERAL_AREA,
            person_id: $person?->id,
            person: $person?->first_name,
            amount: null,
            estimated: false,
            reminder_done: true,
            reminder: $data,
            href: $this->list->hrefOf($reminder, $subject),
        );
    }

    /**
     * The entry of a task.
     *
     * @param  Reminder  $reminder  the task, with `person` loaded
     * @param  ReminderSubjectData|null  $subject  the card it is linked to
     * @param  AgendaReminderData  $data  its reminder as the Agenda shows it
     * @param  bool  $done  whether the task is done
     */
    private function entry(Reminder $reminder, ?ReminderSubjectData $subject, AgendaReminderData $data, bool $done = false): AgendaEntryData
    {
        $person = $reminder->person instanceof Person ? $reminder->person : null;

        return new AgendaEntryData(
            key: 'task:'.$reminder->id,
            kind: 'task',
            date: $reminder->day()->toDateString(),
            title: $reminder->title,
            subtitle: $subject instanceof ReminderSubjectData ? $subject->label : '',
            name: $reminder->title,
            detail: __('Task'),
            area: $subject instanceof ReminderSubjectData ? $subject->area : AgendaData::GENERAL_AREA,
            person_id: $person?->id,
            person: $person?->first_name,
            amount: null,
            estimated: false,
            reminder_done: $done,
            reminder: $data,
            href: route('reminders.edit', $reminder),
            notes: $reminder->notes,
        );
    }
}
