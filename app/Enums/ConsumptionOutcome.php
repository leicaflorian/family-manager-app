<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What came of reading the consumption of a bill (`ai_readings.outcome`).
 */
enum ConsumptionOutcome: string
{
    use HasLabel;

    case Saved = 'saved';
    case NotABill = 'not_a_bill';
    case NoConsumption = 'no_consumption';
    case WrongUnit = 'wrong_unit';
    case OutOfRange = 'out_of_range';
    case BadPeriod = 'bad_period';
}
