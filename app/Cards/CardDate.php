<?php

declare(strict_types=1);

namespace App\Cards;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A date of a card shown in its tiles, with whether it is a deadline: a past
 * deadline makes the card expired, a past start or purchase does not.
 *
 * A deadline can still stop counting as overdue (the card has nothing left to
 * do for it) without losing its red tile: `overdueWhile` says when it counts.
 */
final readonly class CardDate
{
    /**
     * @param  string  $column  the date column
     * @param  string  $label  the label, already translated
     * @param  bool  $expires  whether the date is a deadline (its tile turns red when it is past)
     * @param  (Closure(Model): bool)|null  $overdueWhile  whether, on this card, a past date still makes it overdue; null means always
     */
    public function __construct(
        public string $column,
        public string $label,
        public bool $expires,
        public ?Closure $overdueWhile = null,
    ) {}

    /**
     * Whether a past date makes the card overdue: it is a deadline and there is
     * still something to do about it.
     *
     * @param  Model  $card  the card the date belongs to
     */
    public function countsAsOverdue(Model $card): bool
    {
        return $this->expires && (! $this->overdueWhile instanceof Closure || ($this->overdueWhile)($card));
    }
}
