<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An area with its cards, by name and with the closed ones last.
 */
#[TypeScript]
final class AreaData extends Data
{
    /**
     * @param  string  $area  the value of the area, for example `home`
     * @param  string  $label  the name of the area
     * @param  int  $count  the cards that are not closed
     * @param  int  $overdue  the cards with an expiring date already past
     * @param  list<CardData>  $cards  the cards of the area
     */
    public function __construct(
        public readonly string $area,
        public readonly string $label,
        public readonly int $count,
        public readonly int $overdue,
        #[DataCollectionOf(CardData::class)]
        public readonly array $cards,
    ) {}
}
