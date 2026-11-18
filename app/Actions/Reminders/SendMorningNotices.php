<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Cards\CardTypes;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Data\AgendaData;
use App\Enums\Role;
use App\Mail\MorningNoticesMail;
use App\Mail\WeeklySummaryMail;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Reminders\WeeklySummaries;
use App\Support\CurrentFamily;
use App\Support\Reminders\MorningItem;
use App\Support\Reminders\Notices;
use App\Support\Reminders\SubjectLink;
use App\Support\Reminders\WeeklySummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the notices of the day for the current family: a row of the bell for each account that gets one, and one
 * email per account with all its notices of the morning (rewrite of `notify` of the Directus extension).
 *
 * On Monday the admins get the weekly summary mail instead of the morning one: the week of the family, with their
 * email notices of the day that are not already in its lists. It goes out once per family and Monday; if the summary
 * cannot be built, the failure is reported and the admins get the morning mail as on any other day. Members and the
 * bell do not change.
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
     * Queues one mail for each account, with all its items, in the language of the account. On Monday the admins get
     * the weekly summary instead, when it could be built.
     *
     * @param  Collection<int, User>  $accounts  the accounts of the family
     * @param  array<int, list<MorningItem>>  $emails  the items of each account, by user id
     * @param  CarbonImmutable  $today  today in Rome
     */
    private function queueMails(Collection $accounts, array $emails, CarbonImmutable $today): void
    {
        $summary = $today->isMonday() ? $this->weeklySummary($today) : null;
        $admins = $summary instanceof WeeklySummary ? $accounts->filter(static fn (User $user): bool => $user->role === Role::Admin) : new Collection;

        // Taken only once the summary exists, so a failed build leaves the Monday to a later run
        if ($summary instanceof WeeklySummary && Cache::add(sprintf('weekly-summary:%d:%s', CurrentFamily::id(), $today->toDateString()), true, now()->addDays(2))) {
            foreach ($admins as $admin) {
                $this->queueWeekly($admin, $summary, $emails[$admin->id] ?? [], $today);
            }
        }

        foreach ($emails as $userId => $items) {
            $user = $accounts->firstWhere('id', $userId);

            if ($user instanceof User && ! $admins->contains('id', $userId)) {
                Mail::to($user)->locale($this->locale($user))->queue(new MorningNoticesMail($user, $items, $today->toDateString()));
            }
        }
    }

    /**
     * The summary of the week for the current family, or null when it cannot be built. The service is resolved
     * here and not in the constructor, so that a failure while resolving it also falls back to the morning mail.
     *
     * @param  CarbonImmutable  $today  the Monday, in Rome
     */
    private function weeklySummary(CarbonImmutable $today): ?WeeklySummary
    {
        try {
            return resolve(WeeklySummaries::class)->week($today);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Queues the weekly mail of an admin, with the notices of the day that are not already in the week. A week with
     * nothing and no notices left sends nothing.
     *
     * @param  User  $admin  the admin
     * @param  WeeklySummary  $summary  the week of the family
     * @param  list<MorningItem>  $items  the email notices of the day of the admin
     * @param  CarbonImmutable  $today  the Monday, in Rome
     */
    private function queueWeekly(User $admin, WeeklySummary $summary, array $items, CarbonImmutable $today): void
    {
        $listed = $summary->reminderIds();
        $notices = array_values(array_filter($items, static fn (MorningItem $item): bool => ! in_array($item->reminderId, $listed, true)));

        if ($summary->isEmpty() && $notices === []) {
            return;
        }

        Mail::to($admin)->locale($this->locale($admin))->queue(new WeeklySummaryMail($admin, $summary, $notices, $today->toDateString()));
    }

    /**
     * The language of the mails of an account.
     *
     * @param  User  $user  the account
     */
    private function locale(User $user): string
    {
        return $user->locale ?? config()->string('app.locale');
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
            CardTypes::find($reminder->link->remindable_type ?? '')?->area()->value ?? AgendaData::GENERAL_AREA,
            $reminder->id,
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
