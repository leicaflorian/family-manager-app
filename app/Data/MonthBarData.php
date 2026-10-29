<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A month of the chart *Expenses, the last 6 months*.
 */
#[TypeScript]
final class MonthBarData extends Data
{
    /**
     * @param  string  $month  the month as `yyyy-mm`
     * @param  float  $spent  what was spent in the month
     * @param  float  $planned  what was planned for the month
     * @param  bool  $current  whether it is the month in progress
     */
    public function __construct(
        public readonly string $month,
        public readonly float $spent,
        public readonly float $planned,
        public readonly bool $current,
    ) {}
}
