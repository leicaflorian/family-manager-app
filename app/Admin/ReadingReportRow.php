<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * One row of the AI readings report: a family (or all of them, for the total) in a month.
 */
final readonly class ReadingReportRow
{
    /**
     * @param  int|null  $familyId  the family; null for a deleted family and for the total
     * @param  string  $label  the family name, "Deleted family" or "Total"
     * @param  int  $readings  readings, cropping excluded
     * @param  int  $calls  every AI call, cropping included
     * @param  int  $inputTokens  tokens sent
     * @param  int  $outputTokens  tokens received
     * @param  float|null  $cost  cost in dollars, null when no call has a price
     * @param  int  $unpriced  calls without a price
     */
    public function __construct(
        public ?int $familyId,
        public string $label,
        public int $readings,
        public int $calls,
        public int $inputTokens,
        public int $outputTokens,
        public ?float $cost,
        public int $unpriced,
    ) {}

    /**
     * The cost as the panel and the command write it: `0,0142 $` with four decimals, `-` with no price. Four
     * decimals because a reading costs a fraction of a cent.
     */
    public function costText(): string
    {
        return $this->cost === null ? '-' : number_format($this->cost, 4, ',', '.').' $';
    }
}
