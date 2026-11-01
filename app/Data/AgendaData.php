<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Area;
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
     * @param  list<OptionData>  $areas  the ten areas in the order of the board, with the names the Agenda gives them
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
        #[DataCollectionOf(OptionData::class)]
        public readonly array $areas,
    ) {}

    /**
     * The ten areas in the order of the board: the ones of the cards, then `general`, which in the Agenda is
     * the area of the documents and of the people.
     *
     * @return list<OptionData>
     */
    public static function areas(): array
    {
        return [
            ...array_map(static fn (Area $area): OptionData => new OptionData($area->value, $area->label()), Area::cases()),
            new OptionData('general', __('People and documents')),
        ];
    }
}
