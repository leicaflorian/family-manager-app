<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the automatic reminders in line with the dates of the cards and documents: one is born when a card gets a
 * date that is still to come, follows the card when its title or person changes, and goes when the date moves or
 * the card stops having it. A reminder already done is history and is never touched.
 *
 * It is the rewrite of `sync` of the Directus extension `reminders-automation`; the key of a reminder is the
 * `source_field`, the card and the `due_date`.
 */
final class SyncReminders
{
    /**
     * Works out the automatic reminders of one card. A card that no longer exists (deleted) loses its open ones.
     *
     * @param  Model  $card  the card or document, in the current family
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function handle(Model $card, CarbonImmutable $today): void
    {
        $sources = DeadlineSources::forModel($card::class);

        if ($sources === []) {
            return;
        }

        DB::transaction(function () use ($card, $today, $sources): void {
            foreach ($sources as $source) {
                $this->syncSource($source, $card, $today);
            }
        });
    }

    /**
     * Works out the automatic reminders of every card of the current family.
     *
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function family(CarbonImmutable $today): void
    {
        foreach (array_unique(array_map(static fn (DeadlineSource $source): string => $source->model, DeadlineSources::all())) as $model) {
            foreach ($model::query()->lazyById() as $card) {
                $this->handle($card, $today);
            }
        }
    }

    /**
     * Removes the open automatic reminders of a card that is going away, with their rows of `remindables`.
     *
     * @param  Model  $card  the card, deleted or about to be (a cascade of the database gives no event)
     */
    public function forget(Model $card): void
    {
        foreach (DeadlineSources::forModel($card::class) as $source) {
            $this->removeOpen($this->existing($source, $card));
        }
    }

    /**
     * Brings the reminders of one kind of deadline of a card in line with the card.
     *
     * @param  DeadlineSource  $source  the kind of deadline
     * @param  Model  $card  the card
     * @param  CarbonImmutable  $today  today in Rome
     */
    private function syncSource(DeadlineSource $source, Model $card, CarbonImmutable $today): void
    {
        $existing = $this->existing($source, $card);

        // The card still has the date, and may or may not want a reminder for it: a late open one stays while the date is there.
        $current = $card->exists ? $source->current($card, $today)?->toDateString() : null;
        $wanted = $card->exists ? $source->wants($card, $today)?->toDateString() : null;

        $this->removeOpen(array_filter($existing, static fn (Reminder $reminder): bool => $reminder->status === ReminderStatus::Open && $reminder->due_date->toDateString() !== $current));

        $locale = config('app.locale');
        $title = __($source->title, [], is_string($locale) ? $locale : null);
        $personId = $source->personColumn === null ? null : $card->getAttribute($source->personColumn);
        $personId = is_int($personId) ? $personId : null;
        $found = false;

        foreach ($existing as $reminder) {
            if ($reminder->due_date->toDateString() !== $current) {
                continue;
            }

            $found = true;

            if ($reminder->status === ReminderStatus::Open && ($reminder->title !== $title || $reminder->person_id !== $personId)) {
                $reminder->update(['title' => $title, 'person_id' => $personId]);
            }
        }

        if ($wanted !== null && ! $found) {
            $reminder = Reminder::query()->create([
                'title' => $title,
                'due_date' => $wanted,
                'status' => ReminderStatus::Open,
                'person_id' => $personId,
                'is_automatic' => true,
                'source_field' => $source->field,
            ]);
            $reminder->link()->create(['remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->getKey()]);
        }
    }

    /**
     * The automatic reminders of a kind of deadline linked to the card, whatever their status.
     *
     * @param  DeadlineSource  $source  the kind of deadline
     * @param  Model  $card  the card
     * @return list<Reminder>
     */
    private function existing(DeadlineSource $source, Model $card): array
    {
        $id = $card->getKey();

        return is_int($id)
            ? array_values(Reminder::query()->automaticFor($source->field, $card->getMorphClass(), [$id])->get()->all())
            : [];
    }

    /**
     * Deletes the open reminders among the given ones, with their rows of `remindables`.
     *
     * @param  array<array-key, Reminder>  $reminders  the reminders to look at
     */
    private function removeOpen(array $reminders): void
    {
        $ids = array_values(array_map(
            static fn (Reminder $reminder): int => $reminder->id,
            array_filter($reminders, static fn (Reminder $reminder): bool => $reminder->status === ReminderStatus::Open),
        ));

        if ($ids === []) {
            return;
        }

        DB::table('remindables')->whereIn('reminder_id', $ids)->delete();
        Reminder::query()->whereKey($ids)->delete();
    }
}
