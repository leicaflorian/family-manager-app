<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ExceptionType;
use App\Services\Occurrences;
use Carbon\CarbonImmutable;

/**
 * One exception to a single occurrence of a planned expense or income, as the
 * recurrence rules read it (see {@see Occurrences}).
 */
final readonly class ScheduleException
{
    /**
     * @param  CarbonImmutable  $occurrenceDate  The original date of the occurrence it changes.
     * @param  ExceptionType  $type  What it does: skip, change the amount or move the date.
     * @param  ?float  $amount  The new amount (amount override only).
     * @param  ?CarbonImmutable  $date  The new date (date override only).
     * @param  ?string  $notes  The reason, if the person wrote one.
     */
    public function __construct(
        public CarbonImmutable $occurrenceDate,
        public ExceptionType $type,
        public ?float $amount = null,
        public ?CarbonImmutable $date = null,
        public ?string $notes = null,
    ) {}
}
