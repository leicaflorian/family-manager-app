<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where the AI reading of a file stands (`files.reading_status`); empty until the file is queued.
 */
enum ReadingStatus: string
{
    use HasLabel;

    case Queued = 'queued';
    case Reading = 'reading';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Off = 'off';
}
