<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Reminders\SyncReminders;
use App\Cards\DeadlineSources;
use App\Models\PlannedExpense;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Works out the reminders of the card a planned expense belongs to, and of the one it belonged to: a card can want
 * its reminder only while a planned expense pays it (the yearly renewal of a subscription).
 */
final readonly class PlannedExpenseObserver
{
    /**
     * @param  SyncReminders  $sync  Writes the reminders.
     */
    public function __construct(private SyncReminders $sync) {}

    /**
     * A planned expense was created or changed.
     *
     * @param  PlannedExpense  $expense  the planned expense
     */
    public function saved(PlannedExpense $expense): void
    {
        $this->syncCards($expense);
    }

    /**
     * A planned expense was deleted.
     *
     * @param  PlannedExpense  $expense  the planned expense
     */
    public function deleted(PlannedExpense $expense): void
    {
        $this->syncCards($expense);
    }

    /**
     * Once the transaction commits, brings in line the card of now and the card of before. The pairs are read now:
     * by then the model has forgotten what it was before the save.
     *
     * @param  PlannedExpense  $expense  the planned expense
     */
    private function syncCards(PlannedExpense $expense): void
    {
        $familyId = $expense->family_id;
        $cards = array_unique([
            $this->pair($expense->card_type, $expense->card_id),
            $this->pair($expense->getOriginal('card_type'), $expense->getOriginal('card_id')),
        ]);

        DB::afterCommit(function () use ($familyId, $cards): void {
            CurrentFamily::using($familyId, function () use ($cards): void {
                $today = CarbonImmutable::today('Europe/Rome');

                foreach ($cards as $pair) {
                    $card = $this->find($pair);

                    if ($card instanceof Model) {
                        $this->sync->handle($card, $today);
                    }
                }
            });
        });
    }

    /**
     * The card of a planned expense, written as one string.
     *
     * @param  mixed  $type  the morph alias, or null
     * @param  mixed  $id  the id, or null
     */
    private function pair(mixed $type, mixed $id): string
    {
        return is_string($type) && is_int($id) ? $type.':'.$id : '';
    }

    /**
     * The card of a pair, when it is of a kind that has deadlines.
     *
     * @param  string  $pair  `type:id`, or an empty string
     */
    private function find(string $pair): ?Model
    {
        if ($pair === '') {
            return null;
        }

        [$type, $id] = explode(':', $pair);
        $class = Relation::getMorphedModel($type);

        return $class !== null && DeadlineSources::forModel($class) !== [] ? $class::query()->find((int) $id) : null;
    }
}
