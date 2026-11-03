<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What an AI call was made for (`ai_readings.purpose`).
 */
enum ReadingPurpose: string
{
    use HasLabel;

    case Document = 'document';
    case MailFilter = 'mail_filter';
    case MailNotice = 'mail_notice';
    case MailFile = 'mail_file';
    case Crop = 'crop';
}
