<?php

declare(strict_types=1);

namespace App\Data\Consumption;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A bill in the table «Bill by bill» of the Consumption page and in the detail of a month.
 */
#[TypeScript]
final class ConsumptionBillData extends Data
{
    /**
     * @param  int  $id  the id of the consumption row
     * @param  int|null  $document_id  the bill it was read from, null for a row written by hand
     * @param  string  $period_start  the first day of the period, `YYYY-MM-DD`
     * @param  string  $period_end  the last day of the period, `YYYY-MM-DD`
     * @param  float  $quantity  the consumption, in the unit
     * @param  string  $unit  `kwh`, `smc` or `m3`
     * @param  float|null  $amount  the total of the bill
     * @param  float|null  $price  euro per unit, null when the consumption is zero or less or there is no amount
     * @param  bool  $paid  whether its document is linked to an expense; a row without a document counts as paid
     * @param  bool  $is_estimated  whether the supplier estimated the consumption
     * @param  bool  $has_adjustments  whether the bill has adjustments
     * @param  bool  $above_average  whether its price is at least 10% above the average price of the 12 months
     * @param  bool  $check  whether its price is out of the usual range, so it is worth a second look
     * @param  string  $source  `reading` or `manual`
     * @param  bool  $can_update  whether the person can change it
     * @param  bool  $can_delete  whether the person can delete it
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $document_id,
        public readonly string $period_start,
        public readonly string $period_end,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly ?float $amount,
        public readonly ?float $price,
        public readonly bool $paid,
        public readonly bool $is_estimated,
        public readonly bool $has_adjustments,
        public readonly bool $above_average,
        public readonly bool $check,
        public readonly string $source,
        public readonly bool $can_update,
        public readonly bool $can_delete,
    ) {}
}
