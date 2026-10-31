<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Agenda of a period: its entries, the people of the family for the filter and the filters.
 */
#[TypeScript]
final class AgendaData extends Data
{
    /**
     * @param  list<AgendaEntryData>  $entries  the entries, by date and then by title
     * @param  int  $months  the period asked, 1, 3, 6 or 12 months
     * @param  string  $from  today, `yyyy-mm-dd`
     * @param  string  $to  the last day of the period, `yyyy-mm-dd`
     * @param  list<PersonData>  $people  the people of the family
     * @param  AgendaFiltersData  $filters  the filters
     */
    public function __construct(
        #[DataCollectionOf(AgendaEntryData::class)]
        public readonly array $entries,
        public readonly int $months,
        public readonly string $from,
        public readonly string $to,
        #[DataCollectionOf(PersonData::class)]
        public readonly array $people,
        public readonly AgendaFiltersData $filters,
    ) {}
}
