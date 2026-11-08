<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MailOutcome;
use App\Models\ReceivedMail;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A mail in the list of *Received mail*: who wrote, the subject, when it arrived and what was done with it.
 */
#[TypeScript]
final class ReceivedMailRowData extends Data
{
    /**
     * @param  int  $id  the id of the mail
     * @param  string  $sender  who wrote: the organization of a known sender, the name in the header or the address
     * @param  string|null  $subject  the subject, null when the mail had none
     * @param  string  $received_at  when it arrived first, ISO with the offset of Rome
     * @param  string  $outcome_label  what was done with it, in a few words («1 file saved · 2 deliveries»)
     * @param  'success'|'info'|'neutral'|'warning'|'danger'  $tone  the colour of the label
     */
    public function __construct(
        public readonly int $id,
        public readonly string $sender,
        public readonly ?string $subject,
        public readonly string $received_at,
        public readonly string $outcome_label,
        #[LiteralTypeScriptType("'success' | 'info' | 'neutral' | 'warning' | 'danger'")]
        public readonly string $tone,
    ) {}

    /**
     * The row of a mail.
     *
     * @param  ReceivedMail  $mail  the mail, best with `files` and `organization` loaded
     */
    public static function fromMail(ReceivedMail $mail): self
    {
        $mail->loadMissing(['files', 'organization']);
        $notice = $mail->noticeKey();
        $outcome = self::outcome($mail->outcome, $mail->deliveries, array_map(
            static fn (array $entry): array => ['notice' => $entry['key'] === $notice, 'gone' => $entry['file'] === null],
            $mail->savedEntries(),
        ));

        return new self(
            id: $mail->id,
            sender: $mail->senderName(),
            subject: $mail->subject,
            received_at: $mail->received_at->setTimezone('Europe/Rome')->toIso8601String(),
            outcome_label: $outcome['label'],
            tone: $outcome['tone'],
        );
    }

    /**
     * What was done with a mail, in a few words, and the colour of the label (`outcomeLabel` of Directus): the
     * notice or the files saved, saying which of them were deleted after; otherwise the outcome. With the number
     * of the deliveries when they are more than one.
     *
     * @param  MailOutcome  $outcome  the outcome of the mail
     * @param  int  $deliveries  how many times Mailgun delivered it
     * @param  list<array{notice: bool, gone: bool}>  $files  the files it saved: whether each is the notice, and whether it was deleted
     * @return array{label: string, tone: 'success'|'info'|'neutral'|'warning'|'danger'}
     */
    public static function outcome(MailOutcome $outcome, int $deliveries, array $files): array
    {
        ['label' => $label, 'tone' => $tone] = $outcome === MailOutcome::Saved && $files !== []
            ? self::savedOutcome($files)
            : ['label' => $outcome->label(), 'tone' => self::tone($outcome)];

        if ($deliveries > 1) {
            $label = __(':label · :count deliveries', ['label' => $label, 'count' => $deliveries]);
        }

        return ['label' => $label, 'tone' => $tone];
    }

    /**
     * The label of a mail that saved files: the notice, saved or deleted, or how many files and how many of them
     * are deleted.
     *
     * @param  non-empty-list<array{notice: bool, gone: bool}>  $files  the files it saved
     * @return array{label: string, tone: 'success'|'info'|'neutral'}
     */
    private static function savedOutcome(array $files): array
    {
        foreach ($files as $file) {
            if ($file['notice']) {
                return $file['gone']
                    ? ['label' => __('Notice deleted'), 'tone' => 'neutral']
                    : ['label' => __('Notice saved'), 'tone' => 'info'];
            }
        }

        $count = count($files);
        $deleted = count(array_filter($files, static fn (array $file): bool => $file['gone']));

        if ($deleted === $count) {
            return ['label' => trans_choice('{1} File deleted|[2,*] :count files deleted', $count, ['count' => $count]), 'tone' => 'neutral'];
        }

        $label = trans_choice('{1} :count file saved|[2,*] :count files saved', $count, ['count' => $count]);

        if ($deleted > 0) {
            $label = trans_choice('{1} :label · :count deleted|[2,*] :label · :count deleted', $deleted, ['label' => $label, 'count' => $deleted]);
        }

        return ['label' => $label, 'tone' => 'success'];
    }

    /**
     * The colour of an outcome without files to count.
     *
     * @param  MailOutcome  $outcome  the outcome
     * @return 'success'|'info'|'neutral'|'warning'|'danger'
     */
    private static function tone(MailOutcome $outcome): string
    {
        return match ($outcome) {
            MailOutcome::Saved => 'success',
            MailOutcome::Confirmation => 'info',
            MailOutcome::Discarded, MailOutcome::Empty => 'neutral',
            MailOutcome::Held => 'warning',
            MailOutcome::Error => 'danger',
        };
    }
}
