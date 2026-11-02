<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry of the Agenda, of the Home and of the calendar: a deadline of a card
 * or a document, an unpaid due date of a planned expense or a due date of a
 * planned income that has not arrived.
 */
#[TypeScript]
final class AgendaEntryData extends Data
{
    /**
     * @param  string  $key  stable and unique: `deadline:<field>:<id>:<date>`, `payment:<planned id>:<original date>` or `income:<planned id>:<original date>`
     * @param  string  $kind  `deadline`, `payment` or `income`
     * @param  string  $date  the day, `yyyy-mm-dd`
     * @param  string  $title  what expires or what to pay, for example «Bolletta gas»
     * @param  string  $subtitle  the card or the document, for example «Gas - Enel»; empty when there is nothing to say
     * @param  string  $name  the thing, the first line of the Agenda: the card («Gas - Enel»), the document, or the title of a payment without a card
     * @param  string  $detail  what happens to it, the line below: «Bolletta gas», «Policy renewal», «Document expiry», or the organization of a payment without a card
     * @param  string  $area  the area, for example `home`; `general` for documents and items without a card
     * @param  int|null  $person_id  the id of the person it belongs to
     * @param  string|null  $person  the first name of that person
     * @param  float|null  $amount  the amount of a payment or an income; null for a deadline
     * @param  bool  $estimated  whether the amount is an estimate
     * @param  bool  $reminder_done  whether the automatic reminder of the deadline is done
     * @param  AgendaReminderData|null  $reminder  the automatic reminder of the deadline, null when there is none
     * @param  string  $href  the card or the document of a deadline; *A due date* with *I paid it* open for a payment or an income
     */
    public function __construct(
        public readonly string $key,
        public readonly string $kind,
        public readonly string $date,
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $name,
        public readonly string $detail,
        public readonly string $area,
        public readonly ?int $person_id,
        public readonly ?string $person,
        public readonly ?float $amount,
        public readonly bool $estimated,
        public readonly bool $reminder_done,
        public readonly ?AgendaReminderData $reminder,
        public readonly string $href,
    ) {}
}
