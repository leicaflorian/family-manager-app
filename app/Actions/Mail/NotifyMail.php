<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Enums\MailOutcome;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\Mail\AttachmentRules;
use App\Support\Mail\NoticeText;

/**
 * The one notice of the bell for a received mail, after its readings: a row of `reminder_notices` without a
 * reminder for each account the family chose, with a line for each saved file and what its reading says. There is
 * no email: as on Directus, the notice of the mail is only in the app. Runs in the family of the mail.
 */
final readonly class NotifyMail
{
    /**
     * Writes the notice, once per mail. Nothing when no file was saved, nor for the notice of a mail without
     * attachments when the family wants only the mails with attachments; the confirmation of Gmail always goes, with
     * its code (decision 8).
     *
     * @param  ReceivedMail  $mail  the mail
     */
    public function handle(ReceivedMail $mail): void
    {
        $settings = FamilySetting::query()->first();

        if (! $settings instanceof FamilySetting || ReminderNotice::query()->where('received_mail_id', $mail->id)->exists()) {
            return;
        }

        $files = $mail->files()->orderBy('id')->get();
        $isNotice = $files->count() === 1 && $files->first()?->mail_key === AttachmentRules::mailKey($mail->event_id, -1);
        $confirmation = $mail->outcome === MailOutcome::Confirmation;

        if (! $confirmation && ! NoticeText::shouldNotify($files->isNotEmpty(), $isNotice, $settings->mail_inbox_notify_attachments_only)) {
            return;
        }

        $lines = $confirmation ? $this->codeLines($mail) : array_values($files->map($this->lineOf(...))->all());

        $settings->notifyUsers()->each(static fn (User $user): ReminderNotice => ReminderNotice::query()->create([
            'user_id' => $user->id,
            'received_mail_id' => $mail->id,
            'lines' => $lines,
            'sent_at' => now(),
        ]));
    }

    /**
     * The line of a file: its name, and what its reading says when it was read.
     * «bolletta_ottobre.pdf — Edison, 84,20 €, scade il 30/10/2026».
     *
     * @param  File  $file  the file
     */
    private function lineOf(File $file): string
    {
        $extraction = $file->reading['extraction'] ?? null;

        if (! is_array($extraction)) {
            return $file->original_name;
        }

        $issuer = $extraction['issuer_name'] ?? null;
        $amount = $extraction['total_amount'] ?? null;
        $dueDate = $extraction['due_date'] ?? null;
        $line = NoticeText::readingLine(
            is_string($issuer) ? $issuer : null,
            is_int($amount) || is_float($amount) ? (float) $amount : null,
            is_string($dueDate) ? $dueDate : null,
        );

        return $line === null ? $file->original_name : "{$file->original_name} — {$line}";
    }

    /**
     * The line of the confirmation of Gmail: its code, to copy into Gmail.
     *
     * @param  ReceivedMail  $mail  the confirmation
     * @return list<string>
     */
    private function codeLines(ReceivedMail $mail): array
    {
        return $mail->gmail_code === null ? [] : [NoticeText::translate('Gmail confirmation code: :code', ['code' => $mail->gmail_code])];
    }
}
