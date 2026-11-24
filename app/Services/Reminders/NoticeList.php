<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Data\AgendaData;
use App\Data\ReminderNoticeData;
use App\Enums\Area;
use App\Enums\ReminderStatus;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ReceivedMail;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Support\Reminders\DueText;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * The notices of the bell, for its menu on every page and for *Notices*.
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
            ->shown()
            ->where('user_id', $userId)
            ->with($this->relations())
            ->orderByRaw('read_at IS NOT NULL')
            ->latest('sent_at')
            ->latest('id');
    }

    /**
     * What a row of a notice reads with it: the reminder and its card, the mail without its text (it can be more
     * than a megabyte) and the organization of its sender, or the bank transaction and the payment it is linked to.
     *
     * @return array<int|string, string|Closure>
     */
    private function relations(): array
    {
        return [
            'reminder.link',
            'mail' => static fn (BelongsTo $query): BelongsTo => $query->select(ReceivedMail::SUMMARY_COLUMNS),
            'mail.organization',
            'bankTransaction.expense',
            'bankTransaction.income',
            'bankConnection',
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
        $notice->load($this->relations());

        return $this->rows(new Collection([$notice]), $today)[0]->href;
    }

    /**
     * The rows of some notices, with the things they refer to read together.
     *
     * @param  Collection<int, ReminderNotice>  $notices  the notices, with the relations of `relations()` loaded
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<ReminderNoticeData>
     */
    private function rows(Collection $notices, CarbonImmutable $today): array
    {
        $this->reminders->loadSubjects(new Collection($notices->map(static fn (ReminderNotice $notice): ?Reminder => $notice->reminder)->filter()->unique('id')->values()->all()));

        return array_values($notices->map(function (ReminderNotice $notice) use ($today): ReminderNoticeData {
            $reminder = $notice->reminder;
            $dueDate = $notice->due_date;

            if ($notice->bankTransaction instanceof BankTransaction) {
                return $this->bankRow($notice, $notice->bankTransaction);
            }

            if ($notice->bankConnection instanceof BankConnection) {
                return $this->consentRow($notice, $notice->bankConnection);
            }

            if (! $reminder instanceof Reminder || ! $dueDate instanceof CarbonImmutable) {
                return $this->mailRow($notice);
            }

            $subject = $this->reminders->subject($reminder->link);
            $title = $this->reminders->titleOf($reminder);
            $sentOn = $notice->sent_at->setTimezone('Europe/Rome');

            return new ReminderNoticeData(
                id: $notice->id,
                title: ReminderList::joinTitle($title, $subject?->label),
                subject: $subject?->label,
                area: $subject->area ?? AgendaData::GENERAL_AREA,
                due_text: DueText::when($sentOn->startOfDay(), $dueDate, ! $reminder->is_automatic),
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

    /**
     * The row of a notice without a reminder: a received mail, which it leads to, or the limit of the mails a day,
     * which leads to the address for the bills. The first line of the notice is the line under the title.
     *
     * @param  ReminderNotice  $notice  the notice, with `mail.organization` loaded
     */
    private function mailRow(ReminderNotice $notice): ReminderNoticeData
    {
        $mail = $notice->mail;
        $lines = $notice->lines ?? [];
        $first = $lines[0] ?? null;

        // The route of the address comes with its page (task 12)
        return new ReminderNoticeData(
            id: $notice->id,
            title: $mail instanceof ReceivedMail
                ? __('Arrived by mail · :subject', ['subject' => $mail->subject ?? __('no subject')])
                : __('Too many mails today'),
            subject: $mail?->senderName(),
            area: AgendaData::GENERAL_AREA,
            due_text: is_string($first) ? $first : '',
            sent_at: $notice->sent_at->setTimezone('Europe/Rome')->format('Y-m-d\TH:i'),
            read: $notice->read_at !== null,
            state: null,
            href: $mail instanceof ReceivedMail ? route('mail.show', $mail) : route('mail-settings.edit'),
        );
    }

    /**
     * The row of a payment the bank settled: «Paid by the bank · Luce novembre 2026» (for an income «Arrived in the
     * bank · …»), the first line of the notice under it, leading to the payment. When the payment is gone (*It was not
     * this one*), it is named by the text of the bank and leads to the transactions of the bank.
     *
     * @param  ReminderNotice  $notice  the notice
     * @param  BankTransaction  $transaction  its transaction, with `expense` and `income` loaded
     */
    private function bankRow(ReminderNotice $notice, BankTransaction $transaction): ReminderNoticeData
    {
        $payment = $transaction->movement();
        $first = ($notice->lines ?? [])[0] ?? null;
        $title = $payment->title ?? $transaction->text;

        return new ReminderNoticeData(
            id: $notice->id,
            title: $transaction->kind() === 'expense'
                ? __('Paid by the bank · :title', ['title' => $title])
                : __('Arrived in the bank · :title', ['title' => $title]),
            subject: null,
            area: Area::Banks->value,
            due_text: is_string($first) ? $first : '',
            sent_at: $notice->sent_at->setTimezone('Europe/Rome')->format('Y-m-d\TH:i'),
            read: $notice->read_at !== null,
            state: null,
            href: $payment === null
                ? route('budget.bank.index')
                : route('budget.movements.show', ['kind' => $payment instanceof Expense ? 'expense' : 'income', 'id' => $payment->id]),
        );
    }

    /**
     * The row of a bank whose consent ends or was taken back: «Bank to connect again · BancoPosta», the first line of
     * the notice under it, leading to the banks.
     *
     * @param  ReminderNotice  $notice  the notice
     * @param  BankConnection  $connection  its connection
     */
    private function consentRow(ReminderNotice $notice, BankConnection $connection): ReminderNoticeData
    {
        $first = ($notice->lines ?? [])[0] ?? null;

        return new ReminderNoticeData(
            id: $notice->id,
            title: __('Bank to connect again · :bank', ['bank' => $connection->institution_name]),
            subject: null,
            area: Area::Banks->value,
            due_text: is_string($first) ? $first : '',
            sent_at: $notice->sent_at->setTimezone('Europe/Rome')->format('Y-m-d\TH:i'),
            read: $notice->read_at !== null,
            state: null,
            href: route('banks.index'),
        );
    }
}
