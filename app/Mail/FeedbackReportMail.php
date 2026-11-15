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
            'sender' => $this->report->creator->name ?? __('An account no longer there'),
            'senderEmail' => $this->report->creator->email ?? '',
            'family' => $this->report->family->name,
            'sentAt' => $this->report->created_at?->timezone('Europe/Rome')->format('d/m/Y H:i') ?? '',
        ]);
    }

    /**
     * The file of the report, from the private disk.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->report->file_path === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->report->file_path)
                ->as($this->report->file_name ?? basename($this->report->file_path))
                ->withMime($this->report->file_mime ?? 'application/octet-stream'),
        ];
    }
}
