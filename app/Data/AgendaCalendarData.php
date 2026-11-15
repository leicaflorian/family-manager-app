<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The month of the Agenda calendar: the weeks of its grid, what is still to do in them and what is already done.
 */
#[TypeScript]
final class AgendaCalendarData extends Data
{
    /**
     * @param  string  $month  the month shown, `yyyy-mm`
     * @param  string  $from  the Monday of the first week of the grid, `yyyy-mm-dd`
     * @param  string  $to  the Sunday of the last week of the grid, `yyyy-mm-dd`
     * @param  string  $today  today, `yyyy-mm-dd`
     * @param  list<AgendaEntryData>  $entries  the things still to do inside the grid, by date and then by title
     * @param  list<AgendaEntryData>  $done  the payments made and the reminders done inside the grid, by date and then by title
     */
    public function __construct(
        public readonly string $month,
        public readonly string $from,
        public readonly string $to,
        public readonly string $today,
        #[DataCollectionOf(AgendaEntryData::class)]
        public readonly array $entries,
        #[DataCollectionOf(AgendaEntryData::class)]
        public readonly array $done,
    ) {}
}
