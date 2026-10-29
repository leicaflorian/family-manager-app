<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the page of *A planned expense* shows besides the accounts to pay with.
 */
#[TypeScript]
final class PlannedDetailData extends Data
{
    /**
     * @param  PlannedRowData  $row  the head of the page
     * @param  float  $year_total  the sum of the due dates of the next 12 months, exceptions applied
     * @param  list<int>  $skipped_months  the numbers (1 to 12) of the months skipped in the next 12 months
     * @param  list<DeadlineData>  $next  the late due dates and the following ones, four in all at most
     * @param  list<ExceptionData>  $exceptions  the exceptions, the latest due date first
     * @param  list<CardValueData>  $values  the box *Data*: the category, the organization, the account, the holder and the card
     * @param  list<PaymentYearData>  $payments  the payments by year, the latest year first
     * @param  bool  $can_edit  whether the person can change the planned item and its exceptions
     * @param  bool  $can_pay  whether the person can record a payment
     */
    public function __construct(
        public readonly PlannedRowData $row,
        public readonly float $year_total,
        public readonly array $skipped_months,
        public readonly array $next,
        public readonly array $exceptions,
        public readonly array $values,
        public readonly array $payments,
        public readonly bool $can_edit,
        public readonly bool $can_pay,
    ) {}
}
