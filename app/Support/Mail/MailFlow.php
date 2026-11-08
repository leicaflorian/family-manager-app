<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Data\Mail\MailScreeningAnswer;
use App\Enums\MailOutcome;

/**
 * The decisions of the work done after Mailgun has its answer. The notes are kept on the mail and shown to the
 * family without a person to write for, so they are in the language of the installation.
 */
final class MailFlow
{
    /** The sender of the mail Gmail sends to confirm a forwarding address (to check against the first real one). */
    public const string GMAIL_FORWARDING = 'forwarding-noreply@google.com';

    /**
     * What the screening says to do. `discard`: the held notice is not saved and the mail stops here. `unread`: the
     * attachments stay saved, nothing is read. `read`: go on. A failed screening (null) counts as unsure: a mail is
     * never thrown away because the model could not answer.
     *
     * @param  MailScreeningAnswer|null  $answer  the answer of the model, null when it failed
     * @param  bool  $held  whether the mail is held as a notice (no attachment)
     * @return array{action: 'discard'|'unread'|'read', note: string|null}
     */
    public static function afterScreen(?MailScreeningAnswer $answer, bool $held): array
    {
        if ($answer?->verdict !== 'discard') {
            return ['action' => 'read', 'note' => null];
        }

        $reason = trim((string) $answer->reason);
        $key = $held ? 'discarded' : 'not read';

        return [
            'action' => $held ? 'discard' : 'unread',
            'note' => $reason === '' ? NoticeText::translate($key) : NoticeText::translate("{$key}: :reason", ['reason' => $reason]),
        ];
    }

    /**
     * What the note of a mail says of a file the model could not read.
     *
     * @param  string  $name  the name of the file
     * @param  string  $message  why the reading failed
     */
    public static function readFailure(string $name, string $message): string
    {
        return NoticeText::translate('AI reading failed: :name: :message', ['name' => $name, 'message' => $message]);
    }

    /**
     * Whether a delivery before already held or discarded the notice of this mail: it is then taken as present.
     *
     * @param  string  $outcome  the value of the outcome of the earlier delivery
     */
    public static function alreadyHeld(string $outcome): bool
    {
        return in_array($outcome, [MailOutcome::Held->value, MailOutcome::Discarded->value], true);
    }

    /**
     * Whether a mail can be saved as a notice by hand (*Salva come avviso*): the model discarded it or it is still
     * under review, and it saved no file.
     *
     * @param  MailOutcome  $outcome  the outcome of the mail
     * @param  int  $savedFiles  how many files the mail saved
     */
    public static function canSaveAsNotice(MailOutcome $outcome, int $savedFiles): bool
    {
        return $savedFiles === 0 && in_array($outcome, [MailOutcome::Held, MailOutcome::Discarded], true);
    }

    /**
     * Whether the mail is the one Gmail sends to confirm a forwarding address.
     *
     * @param  IncomingMail  $mail  the mail
     */
    public static function isGmailConfirmation(IncomingMail $mail): bool
    {
        return $mail->senderAddress === self::GMAIL_FORWARDING;
    }

    /**
     * The confirmation code of Gmail: the first number of 9 digits in the subject, otherwise in the text.
     *
     * @param  IncomingMail  $mail  the mail
     */
    public static function gmailCode(IncomingMail $mail): ?string
    {
        foreach ([$mail->subject, $mail->plainText] as $text) {
            if (preg_match('/(?<!\d)\d{9}(?!\d)/', $text, $match) === 1) {
                return $match[0];
            }
        }

        return null;
    }
}
