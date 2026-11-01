<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Cards\DeadlineSources;
use App\Models\PlannedExpense;
use App\Models\Remindable;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Which reminders miss the planned expense of their card (*Manca la spesa
 * prevista*): the kind of deadline is paid by a planned expense (road tax,
 * inspection, policy, first and last instalment) and the card has no planned
 * expense in progress.
 */
final class MissingExpenses
{
    /**
     * The reminders that miss their planned expense, with one query for all.
     *
     * @param  Collection<int, Reminder>  $reminders  the reminders to check
     * @param  CarbonImmutable  $today  today in Rome: a planned expense that ends today is still in progress
     * @return array<int, true> the ids of the reminders that miss it
     */
    public function of(Collection $reminders, CarbonImmutable $today): array
    {
        /** @var array<int, array{type: string, id: int}> $cards the card of each reminder that can miss an expense, by reminder id */
        $cards = [];

        foreach (EloquentCollection::make($reminders)->loadMissing('link') as $reminder) {
            $link = $reminder->link;

            if ($link instanceof Remindable && $reminder->source_field !== null && DeadlineSources::find($reminder->source_field)?->hasExpense === true) {
                $cards[$reminder->id] = ['type' => $link->remindable_type, 'id' => $link->remindable_id];
            }
        }

        if ($cards === []) {
            return [];
        }

        $covered = PlannedExpense::query()
            ->where(static function (Builder $query) use ($cards): void {
                foreach (collect($cards)->groupBy('type') as $type => $group) {
                    $query->orWhere(static fn (Builder $pair): Builder => $pair
                        ->where('card_type', $type)
                        ->whereIn('card_id', $group->pluck('id')->all()));
                }
            })
            ->where(static fn (Builder $query): Builder => $query->whereNull('end_date')->orWhere('end_date', '>=', $today->toDateString()))
            ->get(['card_type', 'card_id'])
            ->mapWithKeys(static fn (PlannedExpense $expense): array => [$expense->card_type.':'.$expense->card_id => true]);

        $missing = [];

        foreach ($cards as $reminderId => $card) {
            if (! $covered->has($card['type'].':'.$card['id'])) {
                $missing[$reminderId] = true;
            }
        }

        return $missing;
    }
}
