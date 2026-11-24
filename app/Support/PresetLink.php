<?php

declare(strict_types=1);

namespace App\Support;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Enums\Feature;
use App\Models\Expense;
use App\Models\File;
use App\Models\Income;
use App\Services\FamilyFeatures;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Where an upload comes from (`?link=<type>:<id>`): read from the query only when it is a card of the family
 * (and, for an upload, an expense or an income of it), so nothing of another family is used or echoed.
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

        return self::find($parts[1], (int) $parts[2]);
    }

    /**
     * Where an upload comes from: a card of the family, or an expense or an income of it. The form of a card keeps
     * using {@see self::from()}, which never takes a movement.
     *
     * @param  mixed  $link  the `link` of the query, `<type>:<id>`
     * @return array{type: string, id: int}|null
     */
    public static function fromUpload(mixed $link): ?array
    {
        if (! is_string($link) || preg_match('/^([a-z_]+):(\d+)$/', $link, $parts) !== 1) {
            return null;
        }

        return self::findUpload($parts[1], (int) $parts[2]);
    }

    /**
     * The card, expense or income of a type and an id, or null when the type is unknown or it is not of the family.
     *
     * @param  string  $type  `expense`, `income` or the type of a card
     * @param  int  $id  its id
     * @return array{type: string, id: int}|null
     */
    public static function findUpload(string $type, int $id): ?array
    {
        // An expense or an income is a page of the Budget: with it off an upload does not come from one
        $budget = resolve(FamilyFeatures::class)->on(Feature::Budget);

        return match ($type) {
            'expense' => $budget && Expense::query()->whereKey($id)->exists() ? ['type' => 'expense', 'id' => $id] : null,
            'income' => $budget && Income::query()->whereKey($id)->exists() ? ['type' => 'income', 'id' => $id] : null,
            default => self::find($type, $id),
        };
    }

    /**
     * A movement as a link, for the files uploaded from its page.
     *
     * @param  Expense|Income  $movement  the expense or income
     * @return array{type: string, id: int}
     */
    public static function movement(Expense|Income $movement): array
    {
        return ['type' => $movement instanceof Expense ? 'expense' : 'income', 'id' => ModelId::of($movement)];
    }

    /**
     * The page a link leads back to: the card, or the expense or income.
     *
     * @param  array{type: string, id: int}  $link  the link
     */
    public static function route(array $link): string
    {
        return in_array($link['type'], ['expense', 'income'], true)
            ? route('budget.movements.show', ['kind' => $link['type'], 'id' => $link['id']])
            : route('cards.show', $link);
    }

    /**
     * The card of a type and an id, or null when the type is unknown or the card
     * is not of the family (or no longer exists).
     *
     * @param  string  $type  the type of the card (`vehicle`)
     * @param  int  $id  the id of the card
     * @return array{type: string, id: int}|null
     */
    public static function find(string $type, int $id): ?array
    {
        $card = CardTypes::find($type);

        if (! $card instanceof CardType || ! $card->model()::query()->whereKey($id)->exists()) {
            return null;
        }

        return ['type' => $card->type(), 'id' => $id];
    }

    /**
     * The pair of columns of a card as the forms send it, `<type>:<id>`.
     *
     * @param  string|null  $type  the type of the card (`card_type`)
     * @param  int|null  $id  the id of the card (`card_id`)
     * @return string|null the card as `<type>:<id>`, null when one of the two is empty
     */
    public static function key(?string $type, ?int $id): ?string
    {
        return $type === null || $id === null ? null : $type.':'.$id;
    }

    /**
     * Where a file of *Upload* was uploaded from, while it is still one of the family.
     *
     * @param  File  $file  the waiting file
     * @return array{type: string, id: int}|null
     */
    public static function ofFile(File $file): ?array
    {
        return $file->link_type === null || $file->link_id === null ? null : self::findUpload($file->link_type, $file->link_id);
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
        $preset = self::fromUpload($link);

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
        $link = self::card($type, $card);

        return ['link' => $link['type'].':'.$link['id']];
    }

    /**
     * A card as a link, for the files uploaded with it.
     *
     * @param  CardType  $type  the type of the card
     * @param  Model  $card  the card
     * @return array{type: string, id: int}
     *
     * @throws InvalidArgumentException if the card has no numeric id
     */
    public static function card(CardType $type, Model $card): array
    {
        return ['type' => $type->type(), 'id' => ModelId::of($card)];
    }
}
