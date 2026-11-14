<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Area;
use App\Services\FamilyAreas;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Agenda of a period: its entries, the people of the family for the filter and the filters.
 */
#[TypeScript]
final class AgendaData extends Data
{
    /** The area of the entries that belong to no card: documents, people and payments of no card. */
    public const string GENERAL_AREA = 'general';

    /** The periods the Agenda offers, in months. */
    public const array PERIODS = [1, 3, 6, 12];

    /**
     * @param  list<AgendaEntryData>  $entries  the entries, by date and then by title
     * @param  int  $months  the period asked, 1, 3, 6 or 12 months
     * @param  string  $from  today, `yyyy-mm-dd`
     * @param  string  $to  the last day of the period, `yyyy-mm-dd`
     * @param  list<PersonData>  $people  the people of the family
     * @param  AgendaFiltersData  $filters  the filters
     * @param  list<OptionData>  $areas  the areas in the order of the family, with the names the Agenda gives them
     * @param  int  $overdue  how many rows of *To do* have a day already past, for the switch
     * @param  string  $view  `todo` or `done`
     * @param  list<AgendaEntryData>  $done  the done rows of the page asked, most recent first; empty in the *To do* view
     * @param  int|null  $done_next_page  the next page of the done rows; null when there is none
     * @param  list<int>  $periods  the periods the person can choose, in months
     * @param  list<string>  $family_hidden  the values of the areas the family hid, to tell them apart in the filter
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
        public readonly int $overdue,
        public readonly string $view,
        #[DataCollectionOf(AgendaEntryData::class)]
        public readonly array $done = [],
        public readonly ?int $done_next_page = null,
        public readonly array $periods = self::PERIODS,
        public readonly array $family_hidden = [],
    ) {}

    /**
     * The ten areas for the filters: the visible ones in the order of the family, then the hidden ones, then
     * `general`, which in the Agenda is the area of the documents and of the people.
     *
     * @param  FamilyAreas  $areas  the order and the hidden areas of the current family
     * @return list<OptionData>
     */
    public static function areas(FamilyAreas $areas): array
    {
        return [
            ...array_map(static fn (Area $area): OptionData => new OptionData($area->value, $area->label()), [...$areas->visible(), ...$areas->hidden()]),
            new OptionData(self::GENERAL_AREA, __('People and documents')),
        ];
    }
}
