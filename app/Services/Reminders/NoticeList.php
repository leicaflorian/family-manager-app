<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Data\AgendaData;
use App\Data\ReminderNoticeData;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Support\Reminders\DueText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * The notices of the bell, for its menu on the Home and for *Notices*.
 */
final readonly class NoticeList
{
    public function __construct(private ReminderList $reminders) {}

    /** How many notices the menu of the bell shows. */
    public const int MENU = 10;

    /** How many notices *Notices* shows on a page. */
    public const int PER_PAGE = 20;

    /**
     * The last notices of an account for the menu of the bell: the new ones
     * first, even when older, then the seen ones, the most recent first.
     *
     * @param  int  $userId  the account
     * @param  CarbonImmutable  $today  today in Rome
     * @return array{unread: list<ReminderNoticeData>, seen: list<ReminderNoticeData>}
     */
    public function latest(int $userId, CarbonImmutable $today): array
    {
        return self::split($this->rows($this->query($userId)->limit(self::MENU)->get(), $today));
    }

    /**
     * A page of the notices of an account, in the order of the menu, keeping only
     * those whose row says the text searched, the name of the thing included.
     * The search runs on the rows and not in the database because that name
     * comes from the card, the document or the person each reminder refers to.
     *
     * @param  int  $userId  the account
     * @param  CarbonImmutable  $today  today in Rome
     * @param  string  $search  the text searched, empty for all
     * @param  int  $page  the page, from 1
     * @return LengthAwarePaginator<int, ReminderNoticeData>
     */
    public function page(int $userId, CarbonImmutable $today, string $search, int $page): LengthAwarePaginator
    {
        $rows = $this->rows($this->query($userId)->get(), $today);

        if ($search !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (ReminderNoticeData $row): bool => mb_stripos($row->title, $search) !== false,
            ));
        }

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($rows),
            self::PER_PAGE,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Splits some rows into the new ones and the seen ones, keeping their order.
     *
     * @param  list<ReminderNoticeData>  $rows  the rows
     * @return array{unread: list<ReminderNoticeData>, seen: list<ReminderNoticeData>}
     */
    public static function split(array $rows): array
    {
        return [
            'unread' => array_values(array_filter($rows, static fn (ReminderNoticeData $row): bool => ! $row->read)),
            'seen' => array_values(array_filter($rows, static fn (ReminderNoticeData $row): bool => $row->read)),
        ];
    }

    /**
     * The notices of an account in the order of the bell: the new ones first, each group the most recent first.
     *
     * @param  int  $userId  the account
     * @return Builder<ReminderNotice>
     */
    private function query(int $userId): Builder
    {
        return ReminderNotice::query()
            ->where('user_id', $userId)
            ->with(['reminder.link'])
            ->orderByRaw('read_at IS NOT NULL')
            ->latest('sent_at')
            ->latest('id');
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
                title: ReminderList::joinTitle($title, $subject?->label),
                subject: $subject?->label,
                area: $subject->area ?? AgendaData::GENERAL_AREA,
                due_text: DueText::when($sentOn->startOfDay(), $notice->due_date, ! $reminder->is_automatic),
                sent_at: $sentOn->format('Y-m-d\TH:i'),
                read: $notice->read_at !== null,
                state: match (true) {
                    $reminder->status === ReminderStatus::Done => 'done',
                    $reminder->day()->lt($today) => 'expired',
                    default => null,
                },
                href: $this->reminders->hrefOf($reminder, $subject),
            );
        })->all());
    }
}
