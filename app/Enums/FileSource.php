<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where a file came from (`files.source`).
 */
enum FileSource: string
{
    use HasLabel;

    case Upload = 'upload';
    case Mail = 'mail';
    case Phone = 'phone';
    case Scanner = 'scanner';
}
