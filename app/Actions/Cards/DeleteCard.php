<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Models\Expense;
use App\Models\PlannedExpense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a card with its links: the documents, the reminders and the expenses
 * stay, the rows and the pair of columns that tie them to the card go.
 */
final class DeleteCard
{
    /**
     * Deletes the card, its rows in `documentables` and in `remindables`, and unlinks its expenses and planned expenses, all or none.
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

            foreach ([Expense::class, PlannedExpense::class] as $expenses) {
                $expenses::query()->whereMorphedTo('card', $card)->update(['card_type' => null, 'card_id' => null]);
            }

            $card->delete();
        });
    }
}
