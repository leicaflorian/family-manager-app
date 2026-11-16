<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\PlannedRowData;

/**
 * What narrows *Planned expenses*: a part of the title, a range of amounts and a range of next due dates. A row that
 * does not pass is hidden; an ended row has no next due date, so a range of days hides it.
 */
final readonly class PlannedFilters
{
    /**
     * @param  string|null  $search  a part of the title, trimmed; null when empty
     * @param  float|null  $min  the smallest amount, included
     * @param  float|null  $max  the largest amount, included
     * @param  string|null  $from  the first next due date, `yyyy-mm-dd`, included
     * @param  string|null  $to  the last next due date, `yyyy-mm-dd`, included
     */
    public function __construct(
        public ?string $search = null,
        public ?float $min = null,
        public ?float $max = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    /**
     * Whether a row passes every filter: its title holds the text (any case), its amount is in the range and its next
     * due date is in the range of days.
     *
     * @param  PlannedRowData  $row  the row of a planned expense or income
     */
    public function keeps(PlannedRowData $row): bool
    {
        if ($this->search !== null && mb_stripos($row->title, $this->search) === false) {
            return false;
        }

        if (($this->min !== null && $row->amount < $this->min) || ($this->max !== null && $row->amount > $this->max)) {
            return false;
        }

        if ($this->from === null && $this->to === null) {
            return true;
        }

        return $row->next_date !== null
            && ($this->from === null || $row->next_date >= $this->from)
            && ($this->to === null || $row->next_date <= $this->to);
    }
}
