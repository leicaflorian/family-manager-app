<?php

declare(strict_types=1);

namespace App\Enums;

use App\Data\OptionData;
use App\Enums\Concerns\HasLabel;

/**
 * Who a person is in the family (`people.role`).
 */
enum PersonRole: string
{
    use HasLabel;

    case Parent = 'parent';
    case Child = 'child';
    case Other = 'other';

    /**
     * The roles as the options of a menu on a page.
     *
     * @return list<OptionData>
     */
    public static function optionData(): array
    {
        return OptionData::list(self::options());
    }
}
