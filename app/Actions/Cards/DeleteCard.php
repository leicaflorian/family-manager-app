<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a card with its links: the documents and the reminders stay, the
 * rows that tie them to the card go.
 */
final class DeleteCard
{
    /**
     * Deletes the card, its rows in `documentables` and in `remindables`, all or none.
     *
     * @param  Model  $card  the card to delete
     */
    public function handle(Model $card): void
    {
        DB::transaction(function () use ($card): void {
            foreach (['documentable', 'remindable'] as $link) {
                DB::table($link.'s')
                    ->where($link.'_type', $card->getMorphClass())
                    ->where($link.'_id', $card->getKey())
                    ->delete();
            }

            $card->delete();
        });
    }
}
