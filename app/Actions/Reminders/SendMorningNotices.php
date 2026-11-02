<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Mail\MorningNoticesMail;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Reminders\MorningItem;
use App\Support\Reminders\Notices;
use App\Support\Reminders\SubjectLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the notices of the day for the current family: a row of the bell for each account that gets one, and one
 * email per account with all its notices of the morning (rewrite of `notify` of the Directus extension).
 */
final class SendMorningNotices
{
    /**
     * Looks at each open reminder, writes the bell rows, remembers what was reached and queues the mails. Without
     * any account nothing is sent and nothing is remembered, so the notices wait for the first one.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @return int how many bell notices were created
     */
    public function handle(CarbonImmutable $today): int
    {
        $accounts = User::query()->where('family_id', CurrentFamily::id())->with('person')->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            return 0;
        }

        $groups = ReminderGroup::query()->get();
        $created = 0;
        /** @var array<int, list<MorningItem>> $emails */
        $emails = [];

        try {
            foreach (Reminder::query()->open()->with('link.subject')->orderBy('id')->get() as $reminder) {
                // A postponed reminder is notified again from its new day
                $deadline = CarbonImmutable::parse($reminder->day()->toDateString(), $today->timezone);
                $previous = array_values(array_map(strval(...), array_filter($reminder->notifications_sent ?? [], is_scalar(...))));
                $due = Notices::due($deadline, Notices::rulesFor($reminder, $groups), $previous, $today);

                if ($due['send'] === [] && $due['sent'] === $previous) {
                    continue;
                }

                // The closure only returns what it did: a retry after a deadlock must not add things twice.
                $result = DB::transaction(function () use ($reminder, $due, $accounts, $deadline): array {
                    $reminder->forceFill(['notifications_sent' => $due['sent']])->saveQuietly();
                    $notices = 0;
                    $mailed = [];

                    foreach (Notices::deliveriesOf($due['send'], $accounts) as $delivery) {
                        if ($delivery->app) {
                            ReminderNotice::query()->create([
                                'reminder_id' => $reminder->id,
                                'user_id' => $delivery->user->id,
                                'due_date' => $deadline->toDateString(),
                                'offset' => $due['send'][0]->offset,
                                'sent_at' => now(),
                            ]);
                            $notices++;
                        }

                        if ($delivery->email) {
                            $mailed[] = $delivery->user->id;
                        }
                    }

                    return ['notices' => $notices, 'mailed' => $mailed];
                });

                // Recorded only after the commit, so a mail is never queued for a rolled-back reminder.
                $created += $result['notices'];

                foreach ($result['mailed'] as $userId) {
                    $emails[$userId][] = $this->item($reminder);
                }
            }
        } finally {
            // Reminders already committed are marked as sent, so their mails go out even if a later one fails.
            $this->queueMails($accounts, $emails, $today);
        }

        return $created;
    }

    /**
     * Queues one mail for each account, with all its items, in the language of the account.
     *
     * @param  Collection<int, User>  $accounts  the accounts of the family
     * @param  array<int, list<MorningItem>>  $emails  the items of each account, by user id
     * @param  CarbonImmutable  $today  today in Rome
     */
    private function queueMails(Collection $accounts, array $emails, CarbonImmutable $today): void
    {
        foreach ($emails as $userId => $items) {
            $user = $accounts->firstWhere('id', $userId);

            if ($user instanceof User) {
                Mail::to($user)->locale($user->locale ?? config()->string('app.locale'))->queue(new MorningNoticesMail($user, $items, $today->toDateString()));
            }
        }
    }

    /**
     * The raw values of a reminder for the mail.
     *
     * @param  Reminder  $reminder  the reminder, with its link loaded
     */
    private function item(Reminder $reminder): MorningItem
    {
        $source = $reminder->is_automatic && $reminder->source_field !== null ? DeadlineSources::find($reminder->source_field) : null;
        $subject = $reminder->link?->subject;

        return new MorningItem(
            $source instanceof DeadlineSource ? $source->title : $reminder->title,
            $source instanceof DeadlineSource,
            ! $reminder->is_automatic,
            $subject instanceof Model ? SubjectLink::name($subject) : null,
            $reminder->due_date->toDateString(),
            $this->url($reminder, $subject),
        );
    }

    /**
     * The page of the thing the reminder refers to: its card or document; without one, the reminders.
     *
     * @param  Reminder  $reminder  the reminder, with its link loaded
     * @param  Model|null  $subject  the record it refers to
     */
    private function url(Reminder $reminder, ?Model $subject): string
    {
        $type = $reminder->link?->remindable_type;

        return ($subject instanceof Model && $type !== null ? SubjectLink::href($type, $subject, true) : null) ?? route('reminders.index');
    }
}
