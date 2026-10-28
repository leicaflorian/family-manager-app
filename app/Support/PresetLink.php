<?php

declare(strict_types=1);

namespace App\Support;

use App\Cards\CardType;
use App\Cards\CardTypes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The card an upload comes from (`?link=<type>:<id>`): read from the query only
 * when it is a card of the family, so nothing of another family is used or echoed.
 */
final class PresetLink
{
    /**
     * The card of the query, or null when it is malformed, of an unknown type or
     * not of the family.
     *
     * @param  mixed  $link  the `link` of the query, `<type>:<id>`
     * @return array{type: string, id: int}|null
     */
    public static function from(mixed $link): ?array
    {
        if (! is_string($link) || preg_match('/^([a-z_]+):(\d+)$/', $link, $parts) !== 1) {
            return null;
        }

        $card = CardTypes::find($parts[1]);
        $id = (int) $parts[2];

        if (! $card instanceof CardType || ! $card->model()::query()->whereKey($id)->exists()) {
            return null;
        }

        return ['type' => $card->type(), 'id' => $id];
    }

    /**
     * The query to put back on the next address of the upload: the link when it is
     * valid, nothing otherwise.
     *
     * @param  mixed  $link  the `link` of the query
     * @return array{link?: string}
     */
    public static function query(mixed $link): array
    {
        $preset = self::from($link);

        return $preset === null ? [] : ['link' => $preset['type'].':'.$preset['id']];
    }

    /**
     * The query that opens the upload from a card.
     *
     * @param  CardType  $type  the type of the card
     * @param  Model  $card  the card
     * @return array{link: string}
     *
     * @throws InvalidArgumentException if the card has no numeric id
     */
    public static function of(CardType $type, Model $card): array
    {
        return ['link' => $type->type().':'.ModelId::of($card)];
    }
}
