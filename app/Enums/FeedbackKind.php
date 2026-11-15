<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What a report sent to the owner is about: something that does not work, or an idea.
 */
enum FeedbackKind: string
{
    use HasLabel;

    case Problem = 'problem';
    case Idea = 'idea';
}
