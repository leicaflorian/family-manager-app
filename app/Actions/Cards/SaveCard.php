<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Cards\CardType;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a card of a type or updates one.
 */
final class SaveCard
{
    /**
     * Saves the card.
     *
     * @param  CardType  $type  the description of the card
     * @param  array<string, mixed>  $columns  the validated columns (`SaveCardRequest::columns()`)
     * @param  Model|null  $card  the card to update; a new one is created if null
     * @return Model the saved card
     */
    public function handle(CardType $type, array $columns, ?Model $card = null): Model
    {
        $model = $type->model();
        $card ??= new $model;

        $card->fill($columns)->save();

        return $card;
    }
}
