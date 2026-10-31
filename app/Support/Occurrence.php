<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One due date of a planned expense or income, with its exceptions applied.
 */
final readonly class Occurrence
{
    /**
     * @param  CarbonImmutable  $date  The real date (moved by a date override, if any).
     * @param  CarbonImmutable  $originalDate  The date the rules give: the key of exceptions and payments.
     * @param  float  $amount  The amount due.
     * @param  bool  $estimated  Whether the amount is an estimate: no exception has changed it.
     * @param  ?float  $baseAmount  The amount of the planned item when an exception changed it, otherwise null.
     * @param  ?string  $notes  The notes of the exceptions of this date, joined by « · ».
     * @param  bool  $skipped  Whether an exception skips it: only a lookup that asks for skipped due dates sees one.
     */
    public function __construct(
        public CarbonImmutable $date,
        public CarbonImmutable $originalDate,
        public float $amount,
        public bool $estimated,
        public ?float $baseAmount,
        public ?string $notes,
        public bool $skipped = false,
    ) {}
}
