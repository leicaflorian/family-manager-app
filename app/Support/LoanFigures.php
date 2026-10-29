<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * What a loan works out by itself, as the loan should show it: the last date and the number of instalments (the
 * ones written when there are, else worked out), the total to pay, the cost, and how far the payments are.
 */
final readonly class LoanFigures
{
    /**
     * @param  ?CarbonImmutable  $endDate  The date of the last instalment.
     * @param  ?int  $installmentsCount  The number of instalments.
     * @param  ?float  $totalToPay  The sum of all the instalments.
     * @param  ?float  $cost  The total to pay less the amount financed.
     * @param  ?int  $installmentsPaid  The instalments whose date is not after today.
     * @param  ?int  $installmentsLeft  The instalments still to pay.
     * @param  ?float  $residual  What is left to pay.
     */
    public function __construct(
        public ?CarbonImmutable $endDate,
        public ?int $installmentsCount,
        public ?float $totalToPay,
        public ?float $cost,
        public ?int $installmentsPaid,
        public ?int $installmentsLeft,
        public ?float $residual,
    ) {}
}
