<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * A part of the app a family may or may not have: `FamilyFeatures` says which ones it has.
 */
enum Feature: string
{
    use HasLabel;

    case Budget = 'budget';
    case Mail = 'mail';
    case BankFeed = 'bank_feed';
    case Consumption = 'consumption';
    case Gallery = 'gallery';
}
