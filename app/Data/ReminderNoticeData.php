<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A notice of the bell in *Notices*.
 */
#[TypeScript]
final class ReminderNoticeData extends Data
{
    /**
     * @param  int  $id  the id of the notice
     * @param  string  $title  what to do and the thing it refers to, for example «Renew the policy · Panda car insurance»;
     *                         «Arrived by mail · <subject>» for a mail, «Too many mails today» for the limit, «Paid by the
     *                         bank · <payment>» or «Arrived in the bank · <payment>» for a due date the bank settled
     * @param  string|null  $subject  the thing the reminder refers to, or who wrote the mail; null when it has none
     * @param  string  $area  the area of its colour: the one of a card, `banks` for the bank, `general` otherwise
     * @param  string  $due_text  the line about the deadline as it was on the day the notice went out; for a mail, the limit or the bank, the first line of the notice
     * @param  string  $sent_at  when it went out, `yyyy-mm-ddThh:mm` in the time of Rome
     * @param  bool  $read  whether the person already saw it
     * @param  string|null  $state  what the reminder is today: `expired` when still open and late, `done` when done; null for a mail or the bank
     * @param  string  $href  where the notice leads: the card or the document, or the reminder; the mail, or the address for the bills;
     *                        the payment the bank settled, or the transactions of the bank when it is gone
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $subject,
        public readonly string $area,
        public readonly string $due_text,
        public readonly string $sent_at,
        public readonly bool $read,
        public readonly ?string $state,
        public readonly string $href,
    ) {}
}
