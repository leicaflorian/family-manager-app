<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What became of a received mail.
 */
enum MailOutcome: string
{
    use HasLabel;

    case Saved = 'saved';
    case Empty = 'empty';
    case Held = 'held';
    case Discarded = 'discarded';
    case Error = 'error';
    case Confirmation = 'confirmation';

    /**
     * How good the outcome is, the best first: the outcome kept for a mail delivered more than once is the lowest rank.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Saved => 0,
            self::Confirmation => 1,
            self::Discarded => 2,
            self::Held => 3,
            self::Empty => 4,
            self::Error => 5,
        };
    }
}
