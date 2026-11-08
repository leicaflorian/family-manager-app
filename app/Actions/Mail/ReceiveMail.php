<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Enums\CallOutcome;
use App\Enums\FileSource;
use App\Enums\MailOutcome;
use App\Enums\SkipReason;
use App\Exceptions\Mail\MailFailure;
use App\Jobs\ScreenReceivedMail;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Services\FileStore;
use App\Services\Mailgun\MailgunForm;
use App\Services\Mailgun\MailgunSignature;
use App\Support\CurrentFamily;
use App\Support\Mail\AttachmentRules;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailFlow;
use App\Support\Mail\NoticeText;
use App\Support\Mail\StoredBody;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A call of Mailgun to `POST mail/mailgun`: a mail delivered to one address of the domain for the bills. It finds
 * the family only from that address (`recipient`), and saves the attachments in that family's *To sort*.
 *
 * The checks go in this order, each with its answer (analysis, point 4): no signing key 404; a body over 40 MB 406,
 * looked at from `Content-Length` before the body is read (past `post_max_size` PHP drops the body and the call
 * would look unsigned: a 401 that Mailgun tries again for 8 hours); a body that is not a form or a wrong signature
 * 401; no recipient 200 `other`; an address of nobody 200 `unknown`; over the mails a day of the family 200
 * `limited`. Then the mail is saved inside its family: 200 `saved`, `held`, `confirmation` or `empty`, or 500
 * `error` when the saving fails, so that Mailgun delivers it again and the files already saved are skipped.
 */
final readonly class ReceiveMail
{
    /** The refused calls leave at most one row (and one line of the log) in this many seconds. */
    private const int REFUSED_LOG_SECONDS = 60;

    /** The longest text of a string column of `received_mails`. */
    private const int MAX_COLUMN_CHARS = 255;

    /** The bytes of a file read to know its real type. */
    private const int SNIFF_BYTES = 16;

    /** The MIME type of each real type of an attachment. */
    private const array MIME_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'heic' => 'image/heic'];

    /**
     * @param  FileStore  $files  where the attachments are saved
     * @param  LogMailCall  $log  the log of the calls
     * @param  NotifyMailLimit  $notifyLimit  the notice of the limit of mails a day
     */
    public function __construct(
        private FileStore $files,
        private LogMailCall $log,
        private NotifyMailLimit $notifyLimit,
    ) {}

    /**
     * Answers a call of Mailgun.
     *
     * @param  Request  $request  the call
     */
    public function handle(Request $request): Response
    {
        $key = config('services.mailgun.signing_key');

        if (! is_string($key) || $key === '') {
            return $this->answer(404, 'Not found.');
        }

        if ((int) $request->headers->get('Content-Length', '0') > config()->integer('services.mailgun.max_body_mb') * 1024 * 1024) {
            $this->logRefused('invalid', CallOutcome::Invalid, 406, 'body too large');

            return $this->answer(406, 'Body too large.');
        }

        /** @var array<string, mixed> $fields */
        $fields = $request->request->all();
        $refusal = $this->isForm($request)
            ? MailgunSignature::check($fields, now()->getTimestamp()) ?? $this->wrongSignature($key, $fields)
            : 'body not a form';

        if ($refusal !== null) {
            $this->logRefused('rejected', CallOutcome::Rejected, 401, $refusal);

            return $this->answer(401, 'Invalid signature.');
        }

        $payload = MailgunForm::redact($fields, array_keys($request->headers->all()));
        $mail = MailgunForm::parse($fields, $request->allFiles());

        if (! $mail instanceof IncomingMail) {
            $this->log->handle(CallOutcome::Other, 200, null, null, null, $payload, null);

            return $this->answer(200, 'No recipient.');
        }

        // the one lookup of the family outside its scope: the address the mail was delivered to, exactly
        $settings = FamilySetting::findByMailRecipient($mail->recipient);

        if (! $settings instanceof FamilySetting) {
            // the mail is nobody's: of it only the address stays
            $this->log->handle(CallOutcome::Unknown, 200, null, null, null, ['recipient' => $mail->recipient], null);

            return $this->answer(200, 'Unknown address.');
        }

        $familyId = $settings->family_id;

        return CurrentFamily::using($familyId, fn (): Response => $this->deliver($mail, $familyId, $payload));
    }

    /**
     * Saves the mail in the current family, which the address of the mail chose: its files, its row, and the job
     * that goes on after the answer.
     *
     * @param  IncomingMail  $mail  the mail
     * @param  int  $familyId  the current family
     * @param  array<string, mixed>  $payload  what the log keeps of the call
     */
    private function deliver(IncomingMail $mail, int $familyId, array $payload): JsonResponse
    {
        $known = ReceivedMail::query()->where('event_id', $mail->eventId)->exists();

        // a delivery again of a mail already received is not a new mail: only new mails count
        if (! $known && $this->overLimit()) {
            $this->tellLimit();
            $this->log->handle(CallOutcome::Limited, 200, $mail, $familyId, null, $payload, null);

            return $this->answer(200, 'Too many mails today.');
        }

        /** @var list<File> $stored */
        $stored = [];

        try {
            $result = DB::transaction(function () use ($mail, &$stored): array {
                return $this->save($mail, $stored);
            });
        } catch (Throwable $error) {
            // the error of a query holds the text of the mail: only its safe description is reported and logged
            $failure = MailFailure::from($error);
            report($failure);
            // the rows of the files went back with the transaction: their files go too, Mailgun delivers again
            Storage::disk(FileStore::DISK)->delete(array_map(static fn (File $file): string => $file->path, $stored));
            $this->log->handle(CallOutcome::Error, 500, $mail, $familyId, $this->saveFailure($mail), $payload, $failure->getMessage());

            return $this->answer(500, 'Could not save the attachments.');
        }

        if ($result['screen']) {
            dispatch(new ScreenReceivedMail($result['mail']->id, $familyId))->afterCommit();
        }

        $this->log->handle($result['outcome'], 200, $mail, $familyId, $result['mail']->id, $payload, null, $result['saved']);

        return new JsonResponse(['saved' => $result['saved'], 'skipped' => count($result['mail']->skipped)]);
    }

    /**
     * Saves one delivery: the row of the mail (new, or the one of an earlier delivery, locked), the attachments
     * not saved yet, and the outcome. A notice of a mail without attachments is held, not saved: the job decides.
     *
     * @param  IncomingMail  $mail  the mail
     * @param  list<File>  $stored  filled with the files written, to remove them if the transaction fails
     * @return array{mail: ReceivedMail, outcome: CallOutcome, saved: int, screen: bool}
     *
     * @throws Throwable when a file or a row is not written
     */
    private function save(IncomingMail $mail, array &$stored): array
    {
        $record = ReceivedMail::query()->where('event_id', $mail->eventId)->lockForUpdate()->first();
        $before = $record?->outcome;
        $record ??= $this->newMail($mail);
        $record->save();

        $skipped = [];
        $present = 0;
        $held = false;
        $confirmation = MailFlow::isGmailConfirmation($mail);

        if (! $confirmation) {
            ['skipped' => $skipped, 'present' => $present] = $this->saveAttachments($mail, $record, $stored);
            $held = $stored === [] && $present === 0
                && ! File::query()->where('mail_key', AttachmentRules::mailKey($mail->eventId, -1))->exists()
                && NoticeText::textOf($mail->plainText, $mail->html) !== null
                && (! $before instanceof MailOutcome || ! MailFlow::alreadyHeld($before->value));
        }

        $outcome = match (true) {
            $confirmation => MailOutcome::Confirmation,
            $stored !== [] || $present > 0 => MailOutcome::Saved,
            $held => MailOutcome::Held,
            default => MailOutcome::Empty,
        };

        $record->forceFill([
            'deliveries' => $record->deliveries + 1,
            'outcome' => $this->best($before, $outcome),
            'skipped' => $skipped,
            'gmail_code' => $confirmation ? MailFlow::gmailCode($mail) : $record->gmail_code,
        ])->save();

        return [
            'mail' => $record,
            'outcome' => match (true) {
                $confirmation => CallOutcome::Confirmation,
                $stored !== [] => CallOutcome::Saved,
                $held => CallOutcome::Held,
                default => CallOutcome::Empty,
            },
            'saved' => count($stored),
            // the job goes on only for a mail that has not had it yet: a first delivery, or one after a failure
            'screen' => in_array($outcome, [MailOutcome::Saved, MailOutcome::Held], true)
                && ($before === null || $before === MailOutcome::Error),
        ];
    }

    /**
     * Saves the attachments worth saving and not saved by an earlier delivery.
     *
     * @param  IncomingMail  $mail  the mail
     * @param  ReceivedMail  $record  its row
     * @param  list<File>  $stored  filled with the files written
     * @return array{skipped: list<array{name: string, reason: string}>, present: int} the files left out and why, and how many were there already
     *
     * @throws RuntimeException when the server lost an attachment
     * @throws Throwable when a file is not written
     */
    private function saveAttachments(IncomingMail $mail, ReceivedMail $record, array &$stored): array
    {
        $maxBytes = config()->integer('services.mailgun.max_attachment_mb') * 1024 * 1024;
        $note = $this->noteOf($mail);
        $skipped = [];
        $listed = [];
        $present = 0;

        foreach ($mail->attachments as $attachment) {
            if (! in_array($attachment->uploadError, [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                // the server lost the file (no temporary folder, a partial upload…): Mailgun delivers it again
                throw new RuntimeException("The upload of attachment {$attachment->index} failed with error {$attachment->uploadError}.");
            }

            $head = $attachment->path === '' ? '' : (string) @file_get_contents($attachment->path, false, null, 0, self::SNIFF_BYTES);
            $reason = AttachmentRules::verdict($attachment, $head, $maxBytes);
            $same = AttachmentRules::sameFileKey($attachment);

            if (! $reason instanceof SkipReason && $same !== null && isset($listed[$same])) {
                $reason = SkipReason::Duplicate;
            }

            if ($reason instanceof SkipReason) {
                $skipped[] = ['name' => $attachment->filename, 'reason' => $reason->value];

                continue;
            }

            if ($same !== null) {
                $listed[$same] = true;
            }

            $key = AttachmentRules::mailKey($mail->eventId, $attachment->index);

            // delivered before, whether the file still waits in To sort or is a document already
            if (File::query()->where('mail_key', $key)->exists()) {
                $present++;

                continue;
            }

            $kind = (string) AttachmentRules::sniff($head);
            $stored[] = $this->files->storePath(
                $attachment->path,
                AttachmentRules::fileName($attachment->filename, $kind),
                self::MIME_TYPES[$kind] ?? 'application/octet-stream',
                true,
                FileSource::Mail,
                $note,
                ['received_mail_id' => $record->id, 'mail_key' => $key],
            );
        }

        return ['skipped' => $skipped, 'present' => $present];
    }

    /**
     * The row of a mail at its first delivery, not saved yet.
     *
     * @param  IncomingMail  $mail  the mail
     */
    private function newMail(IncomingMail $mail): ReceivedMail
    {
        return new ReceivedMail([
            'event_id' => $mail->eventId,
            'sender' => mb_substr($mail->sender, 0, self::MAX_COLUMN_CHARS),
            'sender_address' => mb_substr($mail->senderAddress, 0, self::MAX_COLUMN_CHARS),
            'recipient' => mb_substr($mail->recipient, 0, self::MAX_COLUMN_CHARS),
            'recipients' => $mail->recipients,
            'subject' => $mail->subject === '' ? null : $mail->subject,
            'received_at' => now(),
            'deliveries' => 0,
            'outcome' => MailOutcome::Error,
            'notes' => [],
            'skipped' => [],
            'body' => StoredBody::of($mail->plainText, $mail->html),
        ]);
    }

    /**
     * After a failed delivery: the mail is written with the outcome `error`, so the family sees it arrived and
     * the delivery after it counts as the second. A failure here too is only reported.
     *
     * @param  IncomingMail  $mail  the mail
     * @return int|null the id of the mail, null when it could not be written
     */
    private function saveFailure(IncomingMail $mail): ?int
    {
        try {
            $record = ReceivedMail::query()->where('event_id', $mail->eventId)->first() ?? $this->newMail($mail);
            $record->forceFill([
                'deliveries' => $record->deliveries + 1,
                'outcome' => $this->best($record->exists ? $record->outcome : null, MailOutcome::Error),
            ])->save();

            return $record->id;
        } catch (Throwable $error) {
            report(MailFailure::from($error));

            return null;
        }
    }

    /**
     * The better of two outcomes of the same mail (`MailOutcome::rank`).
     *
     * @param  MailOutcome|null  $before  the outcome of the deliveries before, null at the first
     * @param  MailOutcome  $now  the outcome of this delivery
     */
    private function best(?MailOutcome $before, MailOutcome $now): MailOutcome
    {
        return $before instanceof MailOutcome && $before->rank() < $now->rank() ? $before : $now;
    }

    /**
     * Whether the current family has received its mails of today already (day of Rome).
     */
    private function overLimit(): bool
    {
        return ReceivedMail::query()->where('received_at', '>=', today('Europe/Rome'))->count() >= config()->integer('services.mailgun.daily_limit');
    }

    /**
     * Tells the admins about the limit. The notice never changes the answer.
     */
    private function tellLimit(): void
    {
        try {
            $this->notifyLimit->handle(config()->integer('services.mailgun.daily_limit'));
        } catch (Throwable $error) {
            report(MailFailure::from($error));
        }
    }

    /**
     * How a file of the mail arrived, for *To sort*: «Arrivato per posta il 23/10/2026 da …: «…»».
     *
     * @param  IncomingMail  $mail  the mail
     */
    private function noteOf(IncomingMail $mail): string
    {
        return NoticeText::translate('Arrived by mail on :date from :sender: «:subject»', [
            'date' => now('Europe/Rome')->format('d/m/Y'),
            'sender' => $mail->senderAddress,
            'subject' => $mail->subject === '' ? NoticeText::translate('no subject') : $mail->subject,
        ]);
    }

    /**
     * Whether the body is a form, the only kind Mailgun sends.
     *
     * @param  Request  $request  the call
     */
    private function isForm(Request $request): bool
    {
        $type = strtolower((string) $request->headers->get('Content-Type'));

        return str_starts_with($type, 'multipart/form-data') || str_starts_with($type, 'application/x-www-form-urlencoded');
    }

    /**
     * Why a signature that can be checked is refused, or null when it is the one of the key.
     *
     * @param  string  $key  the signing key
     * @param  array<string, mixed>  $fields  the fields of the call
     */
    private function wrongSignature(string $key, array $fields): ?string
    {
        $signed = MailgunSignature::verify(
            $key,
            MailgunForm::text($fields, 'timestamp'),
            MailgunForm::text($fields, 'token'),
            MailgunForm::text($fields, 'signature'),
        );

        return $signed ? null : 'wrong signature';
    }

    /**
     * Logs a refused call, at most once a minute for each kind: anyone can make one, and whoever does not know the
     * key must not be able to fill the log. Nothing of the call is kept.
     *
     * @param  string  $kind  the counter of the minute (`invalid`, `rejected`)
     * @param  CallOutcome  $outcome  the outcome of the row
     * @param  int  $status  the answer
     * @param  string  $reason  why, for the log of the app only
     */
    private function logRefused(string $kind, CallOutcome $outcome, int $status, string $reason): void
    {
        RateLimiter::attempt("mail-inbox:{$kind}", 1, function () use ($outcome, $status, $reason): void {
            Log::warning("[mail] call of Mailgun refused: {$reason}");
            $this->log->handle($outcome, $status, null, null, null, [], $status === 406 ? $reason : null);
        }, self::REFUSED_LOG_SECONDS);
    }

    /**
     * An answer with a short message.
     *
     * @param  int  $status  the HTTP status
     * @param  string  $message  the message, in English: only Mailgun reads it
     */
    private function answer(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
