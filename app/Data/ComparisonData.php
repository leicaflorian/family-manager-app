<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * *Planned and spent*: a month of expenses against the plan, by category, with the last six months and the due dates
 * that nothing has settled yet.
 */
#[TypeScript]
final class ComparisonData extends Data
{
    /**
     * @param  string  $month  the month, `yyyy-mm`
     * @param  float  $planned  the sum of the due dates of the expenses of the month
     * @param  float  $spent  the sum of the expenses dated in the month, planned or not
     * @param  float  $incomes  what came in
     * @param  float  $incomes_planned  the sum of the due dates of the incomes of the month
     * @param  int|null  $days_left  the days that the month still has, only for the month in progress
     * @param  list<CategoryComparisonData>  $categories  one row per main category, in the order of the categories
     * @param  list<MonthBarData>  $months  the last six months, ending with the one asked
     * @param  list<DeadlineData>  $unpaid  the due dates of the expenses of the month that nothing settles, late ones first
     */
    public function __construct(
        public readonly string $month,
        public readonly float $planned,
        public readonly float $spent,
        public readonly float $incomes,
        public readonly float $incomes_planned,
        public readonly ?int $days_left,
        public readonly array $categories,
        public readonly array $months,
        public readonly array $unpaid,
    ) {}
}
