<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Enums\CallOutcome;
use App\Models\MailInboxCall;
use App\Support\Mail\IncomingMail;
use Throwable;

/**
 * Writes one call of Mailgun in the log of the owner (`mail_inbox_calls`). The log never changes the answer to
 * Mailgun: a row that is not written is reported and the call goes on.
 */
final readonly class LogMailCall
{
    /**
     * Writes the row of a call.
     *
     * @param  CallOutcome  $outcome  how the call ended
     * @param  int  $status  the HTTP status of the answer
     * @param  IncomingMail|null  $mail  the mail of the call; null when it is not kept (refused, unknown address)
     * @param  int|null  $familyId  the family the mail reached, null when it reached none
     * @param  int|null  $mailId  the received mail the call delivered
     * @param  array<string, mixed>  $payload  what is kept of the call, already chosen (`MailgunForm::redact`)
     * @param  string|null  $error  what went wrong, never the text of the mail
     * @param  int  $saved  the files saved by this call
     */
    public function handle(CallOutcome $outcome, int $status, ?IncomingMail $mail, ?int $familyId, ?int $mailId, array $payload, ?string $error, int $saved = 0): void
    {
        try {
            MailInboxCall::query()->create([
                'outcome' => $outcome,
                'http_status' => $status,
                'event_id' => $mail?->eventId,
                'attachments' => $mail instanceof IncomingMail ? count($mail->attachments) : 0,
                'saved' => $saved,
                'error' => $error,
                'payload' => $payload,
                'family_id' => $familyId,
                'received_mail_id' => $mailId,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
