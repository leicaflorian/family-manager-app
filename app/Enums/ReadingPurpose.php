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
    /** The rule of a planned item's bank payment: nothing records it any more (the AI writes filters), old rows keep it. */
    case BankRule = 'bank_rule';
    case BankFilter = 'bank_filter';
    case Consumption = 'consumption';
}
