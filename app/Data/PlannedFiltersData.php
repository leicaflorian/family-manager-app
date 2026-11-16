<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\PlannedFilters;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The filters of *Planned expenses*, as the page writes them back into its fields.
 */
#[TypeScript]
final class PlannedFiltersData extends Data
{
    /**
     * @param  string|null  $search  a part of the title
     * @param  float|null  $min  the smallest amount
     * @param  float|null  $max  the largest amount
     * @param  string|null  $from  the first next due date, ISO
     * @param  string|null  $to  the last next due date, ISO
     */
    public function __construct(
        public readonly ?string $search,
        public readonly ?float $min,
        public readonly ?float $max,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {}

    /**
     * The data of the filters.
     *
     * @param  PlannedFilters  $filters  the filters of the request
     */
    public static function fromFilters(PlannedFilters $filters): self
    {
        return new self($filters->search, $filters->min, $filters->max, $filters->from, $filters->to);
    }
}
