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
     * @param  string  $key  stable and unique: `deadline:<field>:<id>:<date>`, `payment:<planned id>:<original date>`, `income:<planned id>:<original date>` or `task:<reminder id>`, or `reminder:<reminder id>` for a done automatic reminder
     * @param  string  $kind  `deadline`, `payment`, `income` or `task`
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
     * @param  string|null  $update_url  where *Update the date* and *Renewed* send the new date of a deadline; null for a payment, an income, or when whoever looks cannot edit the card
     * @param  string|null  $notes  the notes of a task; null for the other kinds and for a task without notes
     * @param  string|null  $account  the name of the account a payment or an income goes through (the one of the planned item, else the default one); null for a deadline or a task
     * @param  string|null  $planned  the planned expense or income of a payment, with how it repeats, for example «Gas - Hera, every 2 months»; null for a deadline or a task
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
        public readonly ?string $update_url = null,
        public readonly ?string $notes = null,
        public readonly ?string $account = null,
        public readonly ?string $planned = null,
    ) {}
}
