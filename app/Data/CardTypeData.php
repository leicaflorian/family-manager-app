<?php

declare(strict_types=1);

namespace App\Data;

use App\Cards\CardType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A type of card, as a choice in *New card*.
 */
#[TypeScript]
final class CardTypeData extends Data
{
    /**
     * @param  string  $type  the type of the morph map
     * @param  string  $label  the name of the type
     * @param  string  $description  what the type is for
     * @param  string  $area  the value of the area of the type
     * @param  string  $area_label  the name of the area, in the current language
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $description,
        public readonly string $area,
        public readonly string $area_label,
    ) {}

    /**
     * The data of a card type.
     *
     * @param  CardType  $card  the description of the type
     */
    public static function fromCardType(CardType $card): self
    {
        return new self($card->type(), $card->label(), $card->description(), $card->area()->value, $card->area()->label());
    }
}
