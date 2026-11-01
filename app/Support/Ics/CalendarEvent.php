<?php

declare(strict_types=1);

namespace App\Support\Ics;

use Carbon\CarbonImmutable;

/**
 * One all-day event of the family's calendar feed. It knows nothing about the models it comes from.
 */
final readonly class CalendarEvent
{
    /**
     * @param  string  $uid  never changes for the same reminder: the calendar updates the event instead of adding one
     * @param  CarbonImmutable  $date  the day of the event
     * @param  string  $summary  the title of the event
     * @param  string  $description  the notes of the event, empty for none
     */
    public function __construct(
        public string $uid,
        public CarbonImmutable $date,
        public string $summary,
        public string $description,
    ) {}
}
