<?php

declare(strict_types=1);

namespace App\Cards;

/**
 * A date of a card shown in its tiles, with whether it is a deadline: a past
 * deadline makes the card expired, a past start or purchase does not.
 */
final readonly class CardDate
{
    /**
     * @param  string  $column  the date column
     * @param  string  $label  the label, already translated
     * @param  bool  $expires  whether the date is a deadline
     */
    public function __construct(
        public string $column,
        public string $label,
        public bool $expires,
    ) {}
}
