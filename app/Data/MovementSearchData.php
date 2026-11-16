<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The result of a search of *Movements*: the matching movements grouped by month, the most recent month first.
 */
#[TypeScript]
final class MovementSearchData extends Data
{
    /**
     * @param  list<MovementMonthData>  $months  the movements shown, at most 200 in all
     * @param  int  $count  how many movements match, also those not shown
     * @param  float  $total  the sum of all the matching movements, also those not shown: incomes positive and expenses negative when both kinds are searched, the plain sum when the search is of one kind
     */
    public function __construct(
        public readonly array $months,
        public readonly int $count,
        public readonly float $total,
    ) {}
}
