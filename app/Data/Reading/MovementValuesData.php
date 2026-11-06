<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The values of the expense or the income a read file proposes.
 */
#[TypeScript]
final class MovementValuesData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  string|null  $title  what it is called
     * @param  float|null  $amount  in euro; for a payment receipt the fee is already added
     * @param  float|null  $fee_amount  the fee of a payment receipt
     * @param  string|null  $date  `YYYY-MM-DD`
     * @param  string|null  $due_date  `YYYY-MM-DD`, the deadline the paper names (an expense only)
     * @param  int|null  $organization_id  who it was paid to, or who paid
     * @param  int|null  $category_id  the expense category (an expense only)
     * @param  int|null  $financial_account_id  the account: the IBAN on the paper, then the commitment's, then the default
     * @param  int|null  $person_id  the holder: the commitment's, otherwise the person of the paper
     * @param  string|null  $income_type  a value of `IncomeType` (an income only)
     * @param  int|null  $employment_id  the job a payslip is about (an income only)
     * @param  string|null  $notes  the summary of the paper, and the period it covers
     * @param  array{id: int, title: string, amount: float, is_estimated: bool}|null  $lead  the planned expense or income this one settles, with its amount for the comparison
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $title,
        public readonly ?float $amount,
        public readonly ?float $fee_amount,
        public readonly ?string $date,
        public readonly ?string $due_date,
        public readonly ?int $organization_id,
        public readonly ?int $category_id,
        public readonly ?int $financial_account_id,
        public readonly ?int $person_id,
        public readonly ?string $income_type,
        public readonly ?int $employment_id,
        public readonly ?string $notes,
        public readonly ?array $lead,
    ) {}
}
