<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What narrows the search of *Movements*: a part of the title, the kind, the account, a range of amounts and a range
 * of days. Without text, amounts or days the page stays in month mode and the filters only narrow the month.
 */
final readonly class MovementFilters
{
    /**
     * @param  string|null  $search  a part of the title, trimmed; null when empty
     * @param  string|null  $kind  `expense` or `income`, null for both
     * @param  int|null  $account  the financial account, null for all
     * @param  float|null  $min  the smallest amount, included
     * @param  float|null  $max  the largest amount, included
     * @param  string|null  $from  the first day, `yyyy-mm-dd`, included
     * @param  string|null  $to  the last day, `yyyy-mm-dd`, included
     */
    public function __construct(
        public ?string $search = null,
        public ?string $kind = null,
        public ?int $account = null,
        public ?float $min = null,
        public ?float $max = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    /**
     * Whether the page lists the matching movements of every month instead of one month: there is a text, an amount
     * or a day to look for. The kind and the account alone only narrow the month.
     */
    public function isSearch(): bool
    {
        return $this->search !== null || $this->min !== null || $this->max !== null || $this->from !== null || $this->to !== null;
    }
}
