<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ReminderStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A reminder in *Reminders*, and in its form.
 */
#[TypeScript]
final class ReminderData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $title  what to do: the title of the kind of deadline for an automatic reminder (in the language of whoever looks), the written one otherwise
     * @param  string  $due_date  the day, `yyyy-mm-dd`
     * @param  ReminderStatus  $status  open or done
     * @param  bool  $is_automatic  whether the app makes it from a date of a card or a document
     * @param  ReminderSubjectData|null  $subject  the thing it refers to
     * @param  ReminderPersonData|null  $person  who it is for
     * @param  int|null  $group_id  the alert group chosen by hand
     * @param  string|null  $next_notice  the day of the next notice after today, `yyyy-mm-dd`; null when no more are due
     * @param  bool  $notice_sent  whether a notice has already gone out for the current date
     * @param  bool  $missing_expense  whether the planned expense of its card is missing
     * @param  string  $href  the card or document of an automatic reminder; the form of a written one
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $due_date,
        public readonly ReminderStatus $status,
        public readonly bool $is_automatic,
        public readonly ?ReminderSubjectData $subject,
        public readonly ?ReminderPersonData $person,
        public readonly ?int $group_id,
        public readonly ?string $next_notice,
        public readonly bool $notice_sent,
        public readonly bool $missing_expense,
        public readonly string $href,
    ) {}
}
