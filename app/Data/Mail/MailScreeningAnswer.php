<?php

declare(strict_types=1);

namespace App\Data\Mail;

use Spatie\LaravelData\Data;

/**
 * What the model answers when asked, from the sender and the subject only, whether a mail is worth reading.
 */
final class MailScreeningAnswer extends Data
{
    /**
     * @param  'keep'|'discard'|'unsure'  $verdict  keep it, throw it away, or the model cannot tell
     * @param  string|null  $reason  why, in a few words
     */
    public function __construct(
        public readonly string $verdict,
        public readonly ?string $reason,
    ) {}
}
