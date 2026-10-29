<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The payments of a planned expense or income in a year, with their total.
 */
#[TypeScript]
final class PaymentYearData extends Data
{
    /**
     * @param  int  $year  the year
     * @param  float  $total  the sum of the payments
     * @param  int  $count  how many payments there are
     * @param  list<MovementData>  $payments  the payments, latest first
     */
    public function __construct(
        public readonly int $year,
        public readonly float $total,
        public readonly int $count,
        public readonly array $payments,
    ) {}
}
