<?php

declare(strict_types=1);

namespace App\Cards;

use App\Enums\Area;
use App\Support\Linkables;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * The description of a type of card: its area, its fields, its dates and the
 * cards it links to. The pages, the form and the validation are built from it,
 * so a field is added in one place only.
 */
abstract class CardType
{
    /**
     * The statuses that close a card: a closed card goes to the bottom of its
     * area and does not count among the active ones.
     *
     * @var list<string>
     */
    public const array CLOSED = [
        'archived', 'expired', 'cancelled', 'closed', 'paid_off', 'terminated', 'ended', 'sold', 'disposed', 'rejected',
    ];

    /**
     * The area the card belongs to.
     */
    abstract public function area(): Area;

    /**
     * The name of the type, for example «Policy».
     */
    abstract public function label(): string;

    /**
     * What the type is for, under its name in *New card*.
     */
    abstract public function description(): string;

    /**
     * The model of the card.
     *
     * @return class-string<Model>
     */
    abstract public function model(): string;

    /**
     * The fields of the form, in order, with the notes at the bottom.
     *
     * @return list<CardField>
     */
    abstract public function fields(): array;

    /**
     * The piece of data that recognises the card in its area, for example the
     * plate of a vehicle; empty when it is not saved.
     *
     * @param  Model  $card  a card of this type
     *
     * @throws InvalidArgumentException if the card is of another type
     */
    abstract public function subtitle(Model $card): string;

    /**
     * The type of the morph map, for example `insurance_policy`.
     *
     * @throws LogicException if the model is not in the morph map
     */
    public function type(): string
    {
        return Linkables::typeOf($this->model()) ?? throw new LogicException($this->model().' is not in the morph map.');
    }

    /**
     * The dates shown in the tiles of the card.
     *
     * @return list<CardDate>
     */
    public function dates(): array
    {
        return [];
    }

    /**
     * The Eloquent relations to other cards, in both directions.
     *
     * @return list<string>
     */
    public function related(): array
    {
        return [];
    }

    /**
     * The notices the saved data gives, shown above the data of the card.
     *
     * @param  Model  $card  a card of this type
     * @return list<string>
     *
     * @throws InvalidArgumentException if the card is of another type
     */
    public function notices(Model $card): array
    {
        return [];
    }

    /**
     * Whether the card is closed: its status is one of `CLOSED`. A card
     * without a status is never closed.
     *
     * @param  Model  $card  a card of this type
     */
    public function isClosed(Model $card): bool
    {
        $status = $card->getAttribute('status');
        $value = $status instanceof BackedEnum ? $status->value : $status;

        return in_array($value, self::CLOSED, true);
    }

    /**
     * The card as its own model, to read its columns.
     *
     * @template T of Model
     *
     * @param  Model  $card  a card of this type
     * @param  class-string<T>  $class  the model of the type
     * @return T
     *
     * @throws InvalidArgumentException if the card is of another type
     */
    protected function card(Model $card, string $class): Model
    {
        if (! $card instanceof $class) {
            throw new InvalidArgumentException($card::class.' is not a '.$class.'.');
        }

        return $card;
    }

    /**
     * The parts that are there, joined by the separator.
     *
     * @param  string  $separator  what goes between two parts
     * @param  string|null  ...$parts  the parts, in order; the empty ones are left out
     */
    protected function join(string $separator, ?string ...$parts): string
    {
        return implode($separator, array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * The notes, the last field of every card.
     *
     * @param  string|null  $label  the label, when the canvas gives another one
     */
    protected function notes(?string $label = null): CardField
    {
        return new CardField('notes', $label ?? __('Notes'), FieldKind::Textarea);
    }

    /**
     * The status of the card, a choice among the values of its enum.
     *
     * @param  class-string<BackedEnum>  $enum  the enum of the status
     */
    protected function status(string $enum): CardField
    {
        return new CardField('status', __('Status'), FieldKind::Choice, enum: $enum);
    }
}
