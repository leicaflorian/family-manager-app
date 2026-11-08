<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Mail\NotifyMail;
use App\Actions\Reading\QueueReading;
use App\Data\Mail\MailScreeningAnswer;
use App\Enums\FileSource;
use App\Enums\MailOutcome;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Services\FileStore;
use App\Services\Mail\MailScreening;
use App\Support\CurrentFamily;
use App\Support\Mail\AttachmentRules;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailFlow;
use App\Support\Mail\NoticeText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Throwable;

/**
 * What is done with a received mail after Mailgun has its answer (analysis, point 9): the question to the AI, the
 * notice held, the readings and the one notice in the bell. It runs in the queue, where nobody is signed in, so it
 * carries the family of the mail and works inside it, whatever family is set around it.
 *
 * The readings are `ReadFile` jobs in one batch, at most 5 (`AttachmentRules::toRead`); the end of the batch writes
 * the failures in the notes of the mail and sends the notice (`afterReadings`). A job that fails leaves the mail
 * as it was, *In esame* for a held notice: the queue tries again, and after the last try *Salva come avviso*
 * recovers it.
 */
#[Tries(3)]
final class ScreenReceivedMail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $mailId  the received mail
     * @param  int  $familyId  the family of the mail
     */
    public function __construct(
        public readonly int $mailId,
        public readonly int $familyId,
    ) {}

    /**
     * Works on the mail inside its family. The confirmation of Gmail only tells the bell (decision 8); a mail that
     * is not saved or held any more (discarded by a try before) is left as it is.
     *
     * @param  MailScreening  $screening  the question to the AI
     * @param  QueueReading  $readings  marks the files to read
     * @param  FileStore  $files  where the held notice is saved
     * @param  NotifyMail  $notify  the notice of the bell
     *
     * @throws Throwable when the held notice is not saved, so that the queue tries again
     */
    public function handle(MailScreening $screening, QueueReading $readings, FileStore $files, NotifyMail $notify): void
    {
        CurrentFamily::using($this->familyId, function () use ($screening, $readings, $files, $notify): void {
            $mail = ReceivedMail::query()->find($this->mailId);

            if (! $mail instanceof ReceivedMail) {
                return;
            }

            if ($mail->outcome === MailOutcome::Confirmation) {
                $notify->handle($mail);

                return;
            }

            if ($mail->outcome === MailOutcome::Saved || $mail->outcome === MailOutcome::Held) {
                $this->screen($mail, $screening, $readings, $files, $notify);
            }
        });
    }

    /**
     * The end of the readings of a mail: the files whose reading failed go in its notes, then the notice of the
     * bell. Called by the batch when its last job ended, in a worker where no family is set.
     *
     * @param  int  $mailId  the mail
     * @param  int  $familyId  the family of the mail
     * @param  list<int>  $fileIds  the files that were read
     */
    public static function afterReadings(int $mailId, int $familyId, array $fileIds): void
    {
        CurrentFamily::using($familyId, static function () use ($mailId, $fileIds): void {
            $mail = ReceivedMail::query()->find($mailId);

            if (! $mail instanceof ReceivedMail) {
                return;
            }

            // A file still `queued` or `reading` failed too: the batch ends before the queue calls `failed()`
            $failures = array_values(File::query()->whereIn('id', $fileIds)->orderBy('id')->get()
                ->filter(static fn (File $file): bool => in_array($file->reading_status, [ReadingStatus::Failed, ReadingStatus::Queued, ReadingStatus::Reading], true))
                ->map(static fn (File $file): string => MailFlow::readFailure(
                    $file->original_name,
                    $file->reading_status === ReadingStatus::Failed && $file->reading_error !== null
                        ? $file->reading_error
                        : NoticeText::translate('The file could not be read.'),
                ))
                ->all());

            self::addNotes($mail, $failures);
            resolve(NotifyMail::class)->handle($mail);
        });
    }

    /**
     * The decisions of `afterScreen`: a held notice to discard ends the mail; files to discard stay unread; otherwise
     * the held notice is saved and the files are read. The answer of the model is kept on the mail as soon as it
     * comes: a try after a failure uses it again instead of asking (and paying) twice, and finds the notice it saved.
     *
     * @param  ReceivedMail  $mail  the mail, saved or held
     * @param  MailScreening  $screening  the question to the AI
     * @param  QueueReading  $readings  marks the files to read
     * @param  FileStore  $files  where the held notice is saved
     * @param  NotifyMail  $notify  the notice of the bell
     *
     * @throws Throwable when the held notice is not saved, or the readings are not sent
     */
    private function screen(ReceivedMail $mail, MailScreening $screening, QueueReading $readings, FileStore $files, NotifyMail $notify): void
    {
        $notice = $this->notice($mail);

        if ($mail->screening === null) {
            // A notice and no answer yet: *Salva come avviso* saved it, and did it all
            if ($notice instanceof File) {
                return;
            }

            $answer = $screening->ask($mail);
            $mail->forceFill(['screening' => ['verdict' => $answer?->verdict, 'reason' => $answer?->reason]])->save();

            // ... or it saved it while the model was answering
            if ($this->notice($mail) instanceof File) {
                return;
            }
        } else {
            $answer = $this->storedAnswer($mail->screening);
        }

        // A notice with an answer was saved by a try before: the mail was held
        $held = $mail->outcome === MailOutcome::Held || $notice instanceof File;
        $decision = MailFlow::afterScreen($answer, $held);
        $notes = $decision['note'] === null ? [] : [$decision['note']];

        if ($decision['action'] === 'discard') {
            $mail->outcome = MailOutcome::Discarded;
            self::addNotes($mail, $notes);

            return;
        }

        self::addNotes($mail, $notes);

        if ($decision['action'] === 'unread') {
            $notify->handle($mail);

            return;
        }

        if ($held) {
            $notice ??= $this->saveNotice($mail, $files);

            if (! $notice instanceof File) {
                $mail->forceFill(['outcome' => MailOutcome::Empty])->save();

                return;
            }

            $mail->forceFill(['outcome' => MailOutcome::Saved])->save();
            $this->read($mail, [$notice], ReadingPurpose::MailNotice, $readings, $notify);

            return;
        }

        $saved = $mail->files()->orderBy('id')->get();
        $ids = AttachmentRules::toRead(array_values($saved->map(static fn (File $file): array => ['id' => $file->id, 'type' => $file->mime_type])->all()));
        $this->read($mail, array_values($saved->whereIn('id', $ids)->all()), ReadingPurpose::MailFile, $readings, $notify);
    }

    /**
     * The answer kept on the mail by a try before: null when the model was not asked (a known sender, the reading
     * off or the readings of the month used up).
     *
     * @param  array<array-key, mixed>  $stored  the column `screening`
     */
    private function storedAnswer(array $stored): ?MailScreeningAnswer
    {
        $verdict = $stored['verdict'] ?? null;
        $reason = $stored['reason'] ?? null;

        return in_array($verdict, ['discard', 'keep', 'unsure'], true) ? new MailScreeningAnswer($verdict, is_string($reason) ? $reason : null) : null;
    }

    /**
     * Sends the readings of some files in one batch, whose end sends the notice; with nothing to read the notice
     * goes now. A file already queued or read (by a try before) is not read again; with the reading off or the
     * readings of the month used up the files are marked `off`.
     *
     * @param  ReceivedMail  $mail  the mail
     * @param  list<File>  $toRead  the files to read
     * @param  ReadingPurpose  $purpose  the notice or an attachment, as `ai_readings` records it
     * @param  QueueReading  $readings  marks the files to read
     * @param  NotifyMail  $notify  the notice of the bell
     *
     * @throws Throwable when the batch is not sent; the files are unread again
     */
    private function read(ReceivedMail $mail, array $toRead, ReadingPurpose $purpose, QueueReading $readings, NotifyMail $notify): void
    {
        // The mails have no person to write for: the messages are in the language of the installation
        $locale = Config::string('app.locale');
        $jobs = [];

        foreach ($toRead as $file) {
            if ($file->reading_status === null && $readings->prepare($file, $locale) === ReadingStatus::Queued) {
                $jobs[] = new ReadFile($file->id, $this->familyId, null, $locale, $purpose);
            }
        }

        if ($jobs === []) {
            $notify->handle($mail);

            return;
        }

        $mailId = $mail->id;
        $familyId = $this->familyId;
        $fileIds = array_map(static fn (ReadFile $job): int => $job->fileId, $jobs);

        try {
            Bus::batch($jobs)
                ->name("mail {$mailId}")
                ->allowFailures()
                ->finally(static function () use ($mailId, $familyId, $fileIds): void {
                    ScreenReceivedMail::afterReadings($mailId, $familyId, $fileIds);
                })
                ->dispatch();
        } catch (Throwable $error) {
            // Not sent: the files go back unread, so that the try after reads them instead of waiting forever
            File::query()->whereIn('id', $fileIds)->update(['reading_status' => null, 'reading_error' => null]);

            throw $error;
        }
    }

    /**
     * Saves the held notice in *To sort*: the text of the mail as `Notice - <subject>.txt`, with the key of the
     * position -1.
     *
     * @param  ReceivedMail  $mail  the held mail
     * @param  FileStore  $files  where it is saved
     * @return File|null the notice, null when the mail kept no text to save
     *
     * @throws RuntimeException if the file is not written
     * @throws Throwable if its row is not written
     */
    private function saveNotice(ReceivedMail $mail, FileStore $files): ?File
    {
        $body = $mail->body ?? [];
        $plain = is_string($body['plainText'] ?? null) ? $body['plainText'] : '';
        $html = is_string($body['html'] ?? null) ? $body['html'] : '';
        $text = NoticeText::textOf($plain, $html);

        if ($text === null) {
            return null;
        }

        $subject = (string) $mail->subject;
        $incoming = new IncomingMail($mail->event_id, $mail->sender, $mail->sender_address, $mail->recipient, [], $subject, null, $plain, $html, []);

        return $files->storeContents(
            NoticeText::file($incoming, $mail->received_at, $text),
            NoticeText::name($subject),
            'text/plain',
            true,
            FileSource::Mail,
            NoticeText::note($mail->sender_address, $mail->subject, $mail->received_at),
            ['received_mail_id' => $mail->id, 'mail_key' => AttachmentRules::mailKey($mail->event_id, -1)],
        );
    }

    /**
     * The notice of the mail, when it is saved already.
     *
     * @param  ReceivedMail  $mail  the mail
     */
    private function notice(ReceivedMail $mail): ?File
    {
        return File::query()->where('mail_key', AttachmentRules::mailKey($mail->event_id, -1))->first();
    }

    /**
     * Adds notes to the mail and saves it, with whatever else changed on it. A note the mail has already (written
     * by a try before) is not added again.
     *
     * @param  ReceivedMail  $mail  the mail
     * @param  list<string>  $notes  the notes to add
     */
    private static function addNotes(ReceivedMail $mail, array $notes): void
    {
        $new = array_values(array_filter($notes, static fn (string $note): bool => ! in_array($note, $mail->notes, true)));

        $mail->forceFill(['notes' => [...$mail->notes, ...$new]])->save();
    }
}
