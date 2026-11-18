<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The movements of one month in the result of a search.
 */
#[TypeScript]
final class MovementMonthData extends Data
{
    /**
     * @param  string  $month  the month, `yyyy-mm`
     * @param  float  $total  the sum of the movements listed here: incomes positive and expenses negative when both kinds are shown, the plain sum when the search is of one kind
     * @param  list<MovementData>  $movements  the movements, the most recent first
     */
    public function __construct(
        public readonly string $month,
        public readonly float $total,
        public readonly array $movements,
    ) {}
}
