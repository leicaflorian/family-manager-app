<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a notice at the top of a page looks: information, something that needs attention, something late.
 */
enum NoticeTone: string
{
    use HasLabel;

    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
}
