<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Reminders\SyncReminders;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the automatic reminders of a card or document in line with it, when it is saved or deleted.
 *
 * A save is handled once the transaction commits, so a card saved by a bigger operation (a loan and its planned
 * expense) is read as it ends up. A delete is handled at once instead: the caller forgets the rows of `remindables`
 * of the card right after, and the open automatic reminders must be found through them first.
 */
final readonly class DeadlineObserver
{
    /**
     * @param  SyncReminders  $sync  Writes the reminders.
     */
    public function __construct(private SyncReminders $sync) {}

    /**
     * The card was created or changed.
     *
     * @param  Model  $card  the card
     */
    public function saved(Model $card): void
    {
        DB::afterCommit(fn () => $this->sync($card));
    }

    /**
     * The card was deleted: its open automatic reminders go.
     *
     * @param  Model  $card  the card
     */
    public function deleted(Model $card): void
    {
        $this->sync($card);
    }

    /**
     * Brings the reminders of the card in line, in the family of the card.
     *
     * @param  Model  $card  the card
     */
    private function sync(Model $card): void
    {
        $familyId = $card->getAttribute('family_id');

        if (! is_int($familyId)) {
            return;
        }

        CurrentFamily::using($familyId, fn () => $this->sync->handle($card, CarbonImmutable::today('Europe/Rome')));
    }
}
