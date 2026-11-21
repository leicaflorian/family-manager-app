<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a utility bill of electricity, gas or water does with its consumption (the box *Record the consumption*):
 * the values with the corrections of the person, the utility the row goes to and what is missing to record it.
 */
#[TypeScript]
final class ConsumptionProposalData extends Data
{
    /**
     * @param  ConsumptionValuesData  $values  the values to record
     * @param  bool  $suggested  whether the box starts ticked: only when the check of the values says they are worth saving and nothing is missing
     * @param  array{id: int, title: string}|null  $utility  the utility of the document; null when there is none yet (or it is created with the file)
     * @param  string|null  $blocked  what is missing to record the consumption; null when nothing is
     * @param  list<string>  $fill  what (`quantity`, `period_start`, `period_end`) the person writes in the box
     * @param  list<string>  $complete  what (`utility`) the person chooses in the links of the document, above
     * @param  bool  $priceWarning  the price per unit is out of the usual range: worth a second look
     */
    public function __construct(
        public readonly ConsumptionValuesData $values,
        public readonly bool $suggested,
        public readonly ?array $utility,
        public readonly ?string $blocked,
        public readonly array $fill = [],
        public readonly array $complete = [],
        public readonly bool $priceWarning = false,
    ) {}
}
