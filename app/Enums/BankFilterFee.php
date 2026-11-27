<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a bank filter finds the commission of a payment (`bank_filters.fee_mode`).
 */
enum BankFilterFee: string
{
    use HasLabel;

    /** The app looks for a small transaction right after the payment. */
    case Auto = 'auto';
    /** A transaction with the words of the filter. */
    case Words = 'words';
    /** The payment has no commission. */
    case None = 'none';
}
