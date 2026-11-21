<?php

declare(strict_types=1);

namespace App\Data\Consumption;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Consumption page of one utility: the types and the utilities to switch to, the 12 months shown, the figures,
 * the sentence, the bills of the period, the bills of the archive still to read and those to check. Built by
 * `App\Services\ConsumptionPage`.
 */
#[TypeScript]
final class ConsumptionPageData extends Data
{
    /**
     * @param  list<array{type: string, label: string}>  $types  the measured types the family has a utility of, electricity first
     * @param  list<array{id: int, title: string, start: string|null, end: string|null}>  $utilities  the utilities of the same type as the one shown, the active first, titled «label · home», with their activation date and, for a utility no longer active, the end of its latest bill, `YYYY-MM-DD`
     * @param  array{id: int, title: string, type: string, unit: string, href: string}  $utility  the utility shown, titled «label · home», with the address of its card
     * @param  string  $from  the first month shown, `YYYY-MM`
     * @param  string  $to  the last month shown, `YYYY-MM`
     * @param  string|null  $previous  the `to` of the 12 months before, null at the first bill
     * @param  string|null  $next  the `to` of the 12 months after, null at the latest bill
     * @param  list<ConsumptionMonthData>  $months  the 12 months shown
     * @param  ConsumptionFiguresData  $figures  the four figures of the 12 months
     * @param  string|null  $sentence  the one sentence of the page, null when none applies
     * @param  list<ConsumptionBillData>  $bills  the bills of the period, the latest first
     * @param  string|null  $month  the month open in the detail, `YYYY-MM`, null when none is asked
     * @param  array{count: int, cost: float, left: int, running: bool, can_read: bool}|null  $archive  the bills of the utility still without consumption: how many, what reading them probably costs in dollars, the readings left this month, whether they are being read and whether they can be; null when there are none
     * @param  list<array{document_id: int, title: string, date: string|null, reason: string}>  $toCheck  the bills read whose consumption could not be saved, with why in words
     * @param  bool  $canCreate  whether the person can add a consumption by hand
     */
    public function __construct(
        #[LiteralTypeScriptType('Array<{ type: App.Enums.UtilityType; label: string }>')]
        public readonly array $types,
        #[LiteralTypeScriptType('Array<{ id: number; title: string; start: string | null; end: string | null }>')]
        public readonly array $utilities,
        #[LiteralTypeScriptType('{ id: number; title: string; type: App.Enums.UtilityType; unit: App.Enums.ConsumptionUnit; href: string }')]
        public readonly array $utility,
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $previous,
        public readonly ?string $next,
        public readonly array $months,
        public readonly ConsumptionFiguresData $figures,
        public readonly ?string $sentence,
        public readonly array $bills,
        public readonly ?string $month,
        #[LiteralTypeScriptType('{ count: number; cost: number; left: number; running: boolean; can_read: boolean } | null')]
        public readonly ?array $archive,
        #[LiteralTypeScriptType('Array<{ document_id: number; title: string; date: string | null; reason: string }>')]
        public readonly array $toCheck,
        public readonly bool $canCreate,
    ) {}
}
