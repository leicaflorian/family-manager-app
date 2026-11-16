<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\MovementFilters;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The filters of the search of *Movements*, as the page writes them back into its fields.
 */
#[TypeScript]
final class MovementFiltersData extends Data
{
    /**
     * @param  string|null  $search  a part of the title
     * @param  string|null  $kind  `expense` or `income`
     * @param  int|null  $account  the financial account
     * @param  float|null  $min  the smallest amount
     * @param  float|null  $max  the largest amount
     * @param  string|null  $from  the first day, ISO
     * @param  string|null  $to  the last day, ISO
     */
    public function __construct(
        public readonly ?string $search,
        public readonly ?string $kind,
        public readonly ?int $account,
        public readonly ?float $min,
        public readonly ?float $max,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {}

    /**
     * The data of the filters.
     *
     * @param  MovementFilters  $filters  the filters of the request
     */
    public static function fromFilters(MovementFilters $filters): self
    {
        return new self($filters->search, $filters->kind, $filters->account, $filters->min, $filters->max, $filters->from, $filters->to);
    }
}
