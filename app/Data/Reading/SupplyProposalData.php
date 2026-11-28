<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What one supply of a bill with more than one (a dual-fuel bill) proposes besides the document they share: the box
 * of its utility, its expense and its consumption.
 */
#[TypeScript]
final class SupplyProposalData extends Data
{
    /**
     * @param  int  $index  the place of the supply in the reading, the key of its choices
     * @param  string|null  $utility_type  a value of `UtilityType`
     * @param  string|null  $supply_point_code  POD or PDR as printed
     * @param  float|null  $amount  the total of the supply in euro
     * @param  string  $heading  the title of its group, in the language of the person («Luce · POD IT 001 E 87654321»)
     * @param  RecordDraftData|null  $record  the box of its utility: the one found to link, or a new one to create
     * @param  MovementProposalData|null  $movement  its expense; null without the Budget
     * @param  ConsumptionProposalData|null  $consumption  its consumption; null without the Budget or without a consumption read
     */
    public function __construct(
        public readonly int $index,
        public readonly ?string $utility_type,
        public readonly ?string $supply_point_code,
        public readonly ?float $amount,
        public readonly string $heading,
        public readonly ?RecordDraftData $record,
        public readonly ?MovementProposalData $movement,
        public readonly ?ConsumptionProposalData $consumption,
    ) {}
}
