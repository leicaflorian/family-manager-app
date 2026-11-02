<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Cards\DeadlineSource;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Support\ModelId;
use App\Support\Reminders\Expiries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * *Update the date* and *Renewed* of a passed deadline: writes the new date on the card or the document. A card that
 * had turned expired with its date is active again when the new date has not passed. *Renewed* also marks done the
 * open automatic reminder of the old date; saving the card syncs the reminders, which makes the one of the new date.
 */
final readonly class UpdateDeadline
{
    /**
     * Changes the date.
     *
     * @param  DeadlineSource  $source  the kind of deadline
     * @param  Model  $card  the card or the document
     * @param  CarbonImmutable  $date  the new date
     * @param  bool  $renewed  whether the deadline was renewed
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function handle(DeadlineSource $source, Model $card, CarbonImmutable $date, bool $renewed, CarbonImmutable $today): void
    {
        DB::transaction(function () use ($source, $card, $date, $renewed, $today): void {
            $old = $card->getAttribute($source->column);

            // Before the save: the sync removes an open reminder whose date is no longer the card's
            if ($renewed && $old instanceof CarbonImmutable) {
                Reminder::query()->automaticFor($source->field, $card->getMorphClass(), [ModelId::of($card)])
                    ->where('status', ReminderStatus::Open)
                    ->whereDate('due_date', $old->toDateString())
                    ->get()
                    ->each(static fn (Reminder $reminder): bool => $reminder->forceFill(['status' => ReminderStatus::Done])->save());
            }

            $card->setAttribute($source->column, $date->toDateString());
            $expiry = Expiries::for($card::class);

            if ($expiry !== null && $expiry['column'] === $source->column && $card->getAttribute('status') === $expiry['to'] && $date->toDateString() >= $today->toDateString()) {
                $card->setAttribute('status', $expiry['from']);
            }

            $card->save();
        });
    }
}
