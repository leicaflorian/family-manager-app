<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The look of the app an account chose: following the device, always light or always dark.
 */
enum Theme: string
{
    use HasLabel;

    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';
}
