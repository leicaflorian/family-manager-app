<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Cards\CardType;
use App\Services\FamilyAreas;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a card of a type or updates one.
 */
final class SaveCard
{
    /**
     * @param  FamilyAreas  $areas  puts the area of a new card back among the visible ones
     */
    public function __construct(private readonly FamilyAreas $areas) {}

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

        $isNew = ! $card->exists;

        $card->fill($columns)->save();

        // A new card in a hidden area would otherwise vanish from the menu right after it is created
        if ($isNew) {
            $this->areas->reveal($type->area());
        }

        return $card;
    }
}
