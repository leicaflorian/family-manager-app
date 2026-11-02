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
     * @param  string  $title  what to do and the thing it refers to, for example «Renew the policy · Panda car insurance»
     * @param  string|null  $subject  the thing the reminder refers to, null when it has none
     * @param  string  $area  the area of its colour: the one of a card, `general` otherwise
     * @param  string  $due_text  the line about the deadline as it was on the day the notice went out
     * @param  string  $sent_at  when it went out, `yyyy-mm-ddThh:mm` in the time of Rome
     * @param  bool  $read  whether the person already saw it
     * @param  string|null  $state  what the reminder is today: `expired` when still open and late, `done` when done
     * @param  string  $href  where the notice leads: the card or the document, or the reminder
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
