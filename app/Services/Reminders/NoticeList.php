<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Data\AgendaData;
use App\Data\ReminderNoticeData;
use App\Data\ReminderSubjectData;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Support\Reminders\DueText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The notices of the bell as *Notices* shows them.
 */
final readonly class NoticeList
{
    public function __construct(private ReminderList $reminders) {}

    /**
     * The notices of an account, the most recent first, in two lists.
     *
     * @param  int  $userId  the account
     * @param  CarbonImmutable  $today  today in Rome
     * @return array{unread: list<ReminderNoticeData>, seen: list<ReminderNoticeData>}
     */
    public function for(int $userId, CarbonImmutable $today): array
    {
        $notices = ReminderNotice::query()
            ->where('user_id', $userId)
            ->with(['reminder.link'])
            ->latest('sent_at')
            ->latest('id')
            ->get();

        $rows = $this->rows($notices, $today);

        return [
            'unread' => array_values(array_filter($rows, static fn (ReminderNoticeData $row): bool => ! $row->read)),
            'seen' => array_values(array_filter($rows, static fn (ReminderNoticeData $row): bool => $row->read)),
        ];
    }

    /**
     * Where a notice leads.
     *
     * @param  ReminderNotice  $notice  the notice
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function hrefOf(ReminderNotice $notice, CarbonImmutable $today): string
    {
        $notice->load('reminder.link');

        return $this->rows(new Collection([$notice]), $today)[0]->href;
    }

    /**
     * The rows of some notices, with the things they refer to read together.
     *
     * @param  Collection<int, ReminderNotice>  $notices  the notices, with `reminder.link` loaded
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<ReminderNoticeData>
     */
    private function rows(Collection $notices, CarbonImmutable $today): array
    {
        $this->reminders->loadSubjects(new Collection($notices->map(static fn (ReminderNotice $notice): Reminder => $notice->reminder)->unique('id')->values()->all()));

        return array_values($notices->map(function (ReminderNotice $notice) use ($today): ReminderNoticeData {
            $reminder = $notice->reminder;
            $subject = $this->reminders->subject($reminder->link);
            $title = $this->reminders->titleOf($reminder);
            $sentOn = $notice->sent_at->setTimezone('Europe/Rome');

            return new ReminderNoticeData(
                id: $notice->id,
                title: $subject instanceof ReminderSubjectData ? "{$title} · {$subject->label}" : $title,
                subject: $subject?->label,
                area: $subject->area ?? AgendaData::GENERAL_AREA,
                due_text: DueText::when($sentOn->startOfDay(), $notice->due_date, ! $reminder->is_automatic),
                sent_at: $sentOn->format('Y-m-d\TH:i'),
                read: $notice->read_at !== null,
                state: match (true) {
                    $reminder->status === ReminderStatus::Done => 'done',
                    $reminder->due_date->lt($today) => 'expired',
                    default => null,
                },
                href: $reminder->is_automatic && $subject?->href !== null ? $subject->href : route('reminders.edit', $reminder, absolute: false),
            );
        })->all());
    }
}
