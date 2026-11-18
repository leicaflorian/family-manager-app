<?php

declare(strict_types=1);

namespace App\Mail;

use App\Data\AgendaEntryData;
use App\Models\User;
use App\Support\Money;
use App\Support\Reminders\DueText;
use App\Support\Reminders\MorningItem;
use App\Support\Reminders\WeeklySummary;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email of the Monday for an admin: what is late, what is to pay and what to do in the week,
 * with the notices of the day, in place of the morning mail.
 */
final class WeeklySummaryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** An account deleted after the mail was queued drops the mail, instead of failing the queue. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  User  $user  the account the mail is for
     * @param  WeeklySummary  $summary  the week of the family
     * @param  list<MorningItem>  $notices  the notices of the day that are not already in the week
     * @param  string  $today  the day of the round, `aaaa-mm-gg`
     */
    public function __construct(
        public readonly User $user,
        public readonly WeeklySummary $summary,
        public readonly array $notices,
        public readonly string $today,
    ) {}

    /**
     * The subject: how much there is to pay, or the plain line of a week without payments.
     */
    public function envelope(): Envelope
    {
        if ($this->summary->total <= 0) {
            return new Envelope(subject: __('Your week at a glance'));
        }

        $amount = Money::euro($this->summary->total);

        return new Envelope(subject: $this->summary->estimated
            ? __('This week: about :amount to pay', ['amount' => $amount])
            : __('This week: :amount to pay', ['amount' => $amount]));
    }

    /**
     * The lines of the email, worded in the language of the account.
     */
    public function content(): Content
    {
        $today = $this->day($this->today);

        return new Content(markdown: 'mail.weekly-summary', with: [
            'name' => $this->user->firstName(),
            'agenda' => route('agenda'),
            'total' => $this->summary->total > 0 ? Money::euro($this->summary->total) : null,
            'late' => $this->rows($this->summary->late),
            'toPay' => $this->rows($this->summary->toPay),
            'tasks' => $this->rows($this->summary->tasks),
            'noticeLines' => array_map(fn (MorningItem $item): array => [
                'title' => $item->translatable ? __($item->title) : $item->title,
                'subject' => $item->subject,
                'when' => DueText::when($today, $this->day($item->dueOn), $item->manual),
                'late' => $this->day($item->dueOn)->lessThanOrEqualTo($today),
                'url' => $item->url,
                'area' => $item->area,
            ], $this->notices),
        ]);
    }

    /**
     * The rows of one section.
     *
     * @param  list<AgendaEntryData>  $entries  the entries of the section
     * @return list<array{day: string, area: string, title: string, subtitle: string, person: ?string, amount: ?string, url: string}>
     */
    private function rows(array $entries): array
    {
        return array_map(fn (AgendaEntryData $entry): array => [
            'day' => $this->day($entry->date)->isoFormat('D ddd'),
            'area' => $entry->area,
            'title' => $entry->title,
            'subtitle' => $entry->subtitle,
            'person' => $entry->person,
            'amount' => $entry->amount === null ? null : Money::euro($entry->amount),
            'url' => $entry->href,
        ], $entries);
    }

    /**
     * A day of the round as a date in Rome.
     *
     * @param  string  $date  `aaaa-mm-gg`
     */
    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Europe/Rome');
    }
}
