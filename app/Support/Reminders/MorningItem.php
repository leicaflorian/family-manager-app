<?php

declare(strict_types=1);

namespace App\Support\Reminders;

/**
 * One notice of the morning email, with raw values: the mail words it in the language of the account when it is
 * rendered, which may be later than when it was queued.
 */
final readonly class MorningItem
{
    /**
     * @param  string  $title  the title of the reminder: an English key when `translatable`, else the text written by hand
     * @param  bool  $translatable  whether the title is a key of an automatic reminder, to translate
     * @param  bool  $manual  whether the reminder was written by hand (it says "by" instead of "due")
     * @param  string|null  $subject  the name of the thing the reminder refers to
     * @param  string  $dueOn  the due date, `aaaa-mm-gg`
     * @param  string  $url  the page of the thing, or of the reminders
     */
    public function __construct(
        public string $title,
        public bool $translatable,
        public bool $manual,
        public ?string $subject,
        public string $dueOn,
        public string $url,
    ) {}
}
