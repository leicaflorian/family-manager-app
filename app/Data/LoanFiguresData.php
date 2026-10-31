<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a loan form shows while the person types: the total to pay and the cost, worked out by the server.
 */
#[TypeScript]
final class LoanFiguresData extends Data
{
    /**
     * @param  float|null  $total_to_pay  the sum of all the instalments, null when the form is not complete
     * @param  float|null  $cost  the total to pay less the amount financed, null when it cannot be worked out
     */
    public function __construct(
        public readonly ?float $total_to_pay,
        public readonly ?float $cost,
    ) {}
}
