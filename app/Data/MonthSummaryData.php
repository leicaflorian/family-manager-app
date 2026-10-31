<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The figures of a month of *Movements*: what came in, what went out and what is left.
 */
#[TypeScript]
final class MonthSummaryData extends Data
{
    /**
     * @param  string  $month  the month, `yyyy-mm`
     * @param  float  $incomes  what came in
     * @param  int  $salaries_in  how many salaries arrived
     * @param  int  $salaries_expected  how many salaries the month expects, those arrived included
     * @param  float  $expenses  what was paid
     * @param  int  $payments  how many expenses were paid
     * @param  float  $balance  incomes minus expenses
     */
    public function __construct(
        public readonly string $month,
        public readonly float $incomes,
        public readonly int $salaries_in,
        public readonly int $salaries_expected,
        public readonly float $expenses,
        public readonly int $payments,
        public readonly float $balance,
    ) {}
}
