<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\Reminders\ReminderList;
use App\Support\Reminders\DueText;
use App\Support\Reminders\MorningItem;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one email of the morning for an account, with all its notices of the day.
 */
final class MorningNoticesMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** An account deleted after the mail was queued drops the mail, instead of failing the queue. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  User  $user  the account the mail is for
     * @param  list<MorningItem>  $items  its notices, at least one
     * @param  string  $today  the day of the round, `aaaa-mm-gg`
     */
    public function __construct(
        public readonly User $user,
        public readonly array $items,
        public readonly string $today,
    ) {}

    /**
     * The subject: the single notice with its title and thing, or how many there are.
     */
    public function envelope(): Envelope
    {
        $today = $this->day($this->today);
        $first = $this->items[0];

        if (count($this->items) > 1) {
            return new Envelope(subject: trans_choice('{1} :count reminder coming up|[2,*] :count reminders coming up', count($this->items)));
        }

        $title = $this->title($first);

        return new Envelope(subject: DueText::subject($today, $this->day($first->dueOn)).': '.ReminderList::joinTitle($title, $first->subject));
    }

    /**
     * The lines of the email, worded in the language of the account.
     */
    public function content(): Content
    {
        $today = $this->day($this->today);

        return new Content(markdown: 'mail.morning-notices', with: [
            'lines' => array_map(fn (MorningItem $item): array => [
                'title' => $this->title($item),
                'subject' => $item->subject,
                'when' => DueText::when($today, $this->day($item->dueOn), $item->manual),
                'url' => $item->url,
            ], $this->items),
        ]);
    }

    /**
     * The title of a notice in the language of the mail.
     *
     * @param  MorningItem  $item  the notice
     */
    private function title(MorningItem $item): string
    {
        return $item->translatable ? __($item->title) : $item->title;
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
