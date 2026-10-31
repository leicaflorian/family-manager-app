<?php

declare(strict_types=1);

namespace App\Queries;

use App\Cards\CardTypes;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Enums\ReminderStatus;
use App\Models\Person;
use App\Models\Reminder;
use App\Support\CardDeadline;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The active deadlines of the cards and the documents of the current family
 * between two days, each with its person, its area and its automatic reminder.
 * It runs one query per kind of deadline, one for its reminders and one for the
 * people, whatever the number of cards.
 */
final class CardDeadlines
{
    /**
     * The deadlines from a day to another one, both included, by date.
     *
     * @param  CarbonImmutable  $from  the first day
     * @param  CarbonImmutable  $to  the last day
     * @param  CarbonImmutable  $today  today in Rome, as the caller has it
     * @return list<CardDeadline>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today): array
    {
        $found = [];

        foreach (DeadlineSources::all() as $source) {
            $cards = $source->query($today)
                ->whereBetween($source->column, [$from->toDateString(), $to->toDateString()])
                ->get();

            if ($cards->isEmpty()) {
                continue;
            }

            $reminders = $this->remindersOf($source, array_values($cards->all()));
            $area = $this->areaOf($source);

            foreach ($cards as $card) {
                $date = CarbonImmutable::instance($this->dateOf($card, $source->column))->startOfDay();
                $found[] = [$source, $card, $date, $area, $reminders[$this->key($card->getKey(), $date)] ?? null];
            }
        }

        $people = $this->peopleOf($found);

        $deadlines = array_map(function (array $item) use ($people): CardDeadline {
            [$source, $card, $date, $area, $reminder] = $item;
            $personId = $this->personIdOf($source, $card);

            return new CardDeadline($source, $card, $date, $personId === null ? null : ($people[$personId] ?? null), $area, $reminder);
        }, $found);

        usort($deadlines, static fn (CardDeadline $a, CardDeadline $b): int => $a->date <=> $b->date);

        return $deadlines;
    }

    /**
     * The automatic reminders of the cards of a kind of deadline, by card and day.
     * When more than one fits, the done one wins.
     *
     * @param  list<Model>  $cards  the cards found
     * @return array<string, Reminder> keyed by `<card id>|<yyyy-mm-dd>`
     */
    private function remindersOf(DeadlineSource $source, array $cards): array
    {
        $ids = array_map(fn (Model $card): int => $this->idOf($card->getKey()), $cards);
        $reminders = [];

        foreach (Reminder::query()->automaticFor($source->field, $cards[0]->getMorphClass(), $ids)->get() as $reminder) {
            $key = $this->key($reminder->getAttribute('card_id'), $reminder->due_date);

            if (! isset($reminders[$key]) || $reminder->status === ReminderStatus::Done) {
                $reminders[$key] = $reminder;
            }
        }

        return $reminders;
    }

    /**
     * The area of the cards of a kind of deadline: the one of their card type, `general` for documents.
     *
     * @param  DeadlineSource  $source  the kind of deadline
     */
    private function areaOf(DeadlineSource $source): string
    {
        $morph = (new $source->model)->getMorphClass();

        foreach (CardTypes::all() as $type) {
            if ((new ($type->model()))->getMorphClass() === $morph) {
                return $type->area()->value;
            }
        }

        return 'general';
    }

    /**
     * The people the deadlines belong to, in one query.
     *
     * @param  list<array{0: DeadlineSource, 1: Model, 2: CarbonImmutable, 3: string, 4: Reminder|null}>  $found  the deadlines found
     * @return array<int, Person> keyed by id
     */
    private function peopleOf(array $found): array
    {
        $ids = [];

        foreach ($found as [$source, $card]) {
            $id = $this->personIdOf($source, $card);

            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        return Person::query()->whereIn('id', array_keys($ids))->get()->keyBy('id')->all();
    }

    /**
     * The id of the person a card belongs to.
     *
     * @param  DeadlineSource  $source  the kind of deadline
     * @param  Model  $card  the card found
     */
    private function personIdOf(DeadlineSource $source, Model $card): ?int
    {
        $id = $source->personColumn === null ? null : $card->getAttribute($source->personColumn);

        return $id === null ? null : $this->idOf($id);
    }

    /**
     * The date of a card as a date object.
     *
     * @param  Model  $card  the card found
     * @param  string  $column  the date column
     */
    private function dateOf(Model $card, string $column): DateTimeInterface
    {
        $date = $card->getAttribute($column);

        return $date instanceof DateTimeInterface ? $date : CarbonImmutable::parse(is_string($date) ? $date : '');
    }

    /**
     * The key that pairs a deadline with its reminder.
     *
     * @param  mixed  $cardId  the id of the card
     * @param  DateTimeInterface  $date  the day of the deadline
     */
    private function key(mixed $cardId, DateTimeInterface $date): string
    {
        return $this->idOf($cardId).'|'.$date->format('Y-m-d');
    }

    /**
     * An id read from a model or a join, as an integer.
     *
     * @param  mixed  $id  the raw id
     */
    private function idOf(mixed $id): int
    {
        return is_numeric($id) ? (int) $id : 0;
    }
}
