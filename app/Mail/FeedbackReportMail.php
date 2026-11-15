<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\FeedbackReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email to the owner with a report from *Report a problem or an idea*: who wrote, from which family and page,
 * the device, the message and the file. Answering it writes to whoever sent the report.
 */
final class FeedbackReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  FeedbackReport  $report  the report to send
     */
    public function __construct(public readonly FeedbackReport $report) {}

    /**
     * The subject, with what the report is about, and the address to answer.
     */
    public function envelope(): Envelope
    {
        $sender = $this->report->creator;

        return new Envelope(
            replyTo: $sender !== null ? [new Address($sender->email, $sender->name)] : [],
            subject: __('New report: :kind', ['kind' => $this->report->kind->label()]),
        );
    }

    /**
     * The text of the email.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.feedback-report', with: [
            'report' => $this->report,
            'message' => $this->plainText($this->report->message),
            'page' => $this->plainText($this->report->page),
            'device' => $this->plainText($this->report->user_agent ?? '-'),
            'sender' => $this->report->creator->name ?? __('An account no longer there'),
            'senderEmail' => $this->report->creator->email ?? '',
            'senderFirstName' => $this->report->creator?->firstName() ?? '',
            'fileName' => $this->report->file_path !== null ? $this->plainText($this->report->file_name ?? basename($this->report->file_path)) : null,
            'family' => $this->report->family->name,
            'errorCounts' => $this->errorCounts(),
            'sentAt' => $this->report->created_at?->timezone('Europe/Rome')->format('d/m/Y H:i') ?? '',
        ]);
    }

    /**
     * The line that counts what the technical part holds, for a problem that has any; null when there is nothing to
     * count (an idea, or a problem sent without details).
     */
    private function errorCounts(): ?string
    {
        $report = $this->report;
        $counts = [
            trans_choice('{1} :count page error|[2,*] :count page errors', $report->pageErrorCount()),
            trans_choice('{1} :count failed request|[2,*] :count failed requests', $report->failedRequestCount()),
            trans_choice('{1} :count server error|[2,*] :count server errors', $report->serverErrorCount()),
        ];

        if ($report->pageErrorCount() + $report->failedRequestCount() + $report->serverErrorCount() === 0) {
            return null;
        }

        return __(':counts — details in the panel', ['counts' => implode(', ', $counts)]);
    }

    /**
     * A text written by the person, shown as it is: the HTML escaped, and the Markdown characters too, so a link or
     * an image in it is never run in the owner's email; its line breaks kept.
     *
     * @param  string  $text  the text as it was sent
     * @return string HTML to print as it is
     */
    private function plainText(string $text): string
    {
        // One pass over the text as it was sent, so an entity written here is never escaped again
        $escaped = strtr($text, [
            '&' => '&amp;', '<' => '&lt;', '>' => '&gt;', '"' => '&quot;', "'" => '&#039;',
            '[' => '&#91;', ']' => '&#93;', '(' => '&#40;', ')' => '&#41;', '!' => '&#33;', '*' => '&#42;',
            '_' => '&#95;', '`' => '&#96;', '#' => '&#35;', '~' => '&#126;', '|' => '&#124;', '\\' => '&#92;',
        ]);

        return nl2br($escaped, false);
    }

    /**
     * The file and the screenshot of the report, from the private disk.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->report->file_path !== null) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->report->file_path)
                ->as($this->report->file_name ?? basename($this->report->file_path))
                ->withMime($this->report->file_mime ?? 'application/octet-stream');
        }

        if ($this->report->screenshot_path !== null) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->report->screenshot_path)
                ->as(basename($this->report->screenshot_path));
        }

        return $attachments;
    }
}
