<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaReminderData;
use App\Data\ReminderSubjectData;
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
     * The entry of a task.
     *
     * @param  Reminder  $reminder  the task, with `person` loaded
     * @param  ReminderSubjectData|null  $subject  the card it is linked to
     * @param  AgendaReminderData  $data  its reminder as the Agenda shows it
     */
    private function entry(Reminder $reminder, ?ReminderSubjectData $subject, AgendaReminderData $data): AgendaEntryData
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
            reminder_done: false,
            reminder: $data,
            href: route('reminders.edit', $reminder),
            notes: $reminder->notes,
        );
    }
}
