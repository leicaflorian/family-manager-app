<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What became of a call of Mailgun: the outcomes of a mail, and the reasons a call was answered without one.
 */
enum CallOutcome: string
{
    use HasLabel;

    case Saved = 'saved';
    case Empty = 'empty';
    case Held = 'held';
    case Discarded = 'discarded';
    case Error = 'error';
    case Confirmation = 'confirmation';
    case Rejected = 'rejected';
    case Invalid = 'invalid';
    case Other = 'other';
    case Unknown = 'unknown';
    case Limited = 'limited';
    case Suspended = 'suspended';
}
