<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The row of a card in *Areas*, *Area* and *Linked to*.
 */
#[TypeScript]
final class CardData extends Data
{
    /**
     * @param  string  $type  the type of the morph map, for example `vehicle`
     * @param  int  $id  the id of the card
     * @param  string  $type_label  the name of the type, for example «Vehicle»
     * @param  string  $label  the name of the card
     * @param  string  $area  the value of the area, for example `vehicles`
     * @param  string  $subtitle  the piece of data that recognises the card, or empty
     * @param  string|null  $next_label  the label of the next date of the card
     * @param  string|null  $next_date  the next date, as `yyyy-mm-dd`
     * @param  bool  $overdue  whether an expiring date has passed on an active card
     * @param  bool  $closed  whether the status closes the card
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $type_label,
        public readonly string $label,
        public readonly string $area,
        public readonly string $subtitle,
        public readonly ?string $next_label,
        public readonly ?string $next_date,
        public readonly bool $overdue,
        public readonly bool $closed,
    ) {}
}
