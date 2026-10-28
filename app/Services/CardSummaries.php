<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\AreaCountData;
use App\Data\AreaData;
use App\Data\CardData;
use App\Enums\Area;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Lists the cards of the current family as rows and groups them by area, for
 * *Areas* and *Area*.
 */
final class CardSummaries
{
    /**
     * The row of a card.
     *
     * @param  Model  $card  a record of one of the 13 models
     * @param  CarbonImmutable|null  $today  today in Rome; read once for a whole list
     *
     * @throws InvalidArgumentException if the record is not a card
     */
    public function row(Model $card, ?CarbonImmutable $today = null): CardData
    {
        $type = CardTypes::for($card);
        $today ??= CarbonImmutable::today('Europe/Rome');
        $closed = $type->isClosed($card);
        $next = $this->nextDate($type, $card, $today);

        return new CardData(
            type: $type->type(),
            id: $this->idOf($card),
            type_label: $type->label(),
            label: $this->labelOf($card),
            area: $type->area()->value,
            area_label: $type->area()->label(),
            subtitle: $type->subtitle($card),
            next_label: $next['label'] ?? null,
            next_date: $next['date'] ?? null,
            overdue: ! $closed && $this->hasPassedDeadline($type, $card, $today),
            closed: $closed,
        );
    }

    /**
     * All the areas, in the order of `Area`, the empty ones too.
     *
     * @return list<AreaData>
     */
    public function areas(): array
    {
        $rows = $this->rows(CardTypes::all());

        return array_map(fn (Area $area): AreaData => $this->areaData($area, $rows[$area->value] ?? []), Area::cases());
    }

    /**
     * All the areas with the number of their active cards, in the order of
     * `Area`: one count per card type, without reading the cards.
     *
     * @return list<AreaCountData>
     */
    public function counts(): array
    {
        return array_map(fn (Area $area): AreaCountData => new AreaCountData(
            area: $area->value,
            label: $area->label(),
            count: array_sum(array_map(static fn (CardType $type): int => $type->countActive(), CardTypes::of($area))),
        ), Area::cases());
    }

    /**
     * One area with its cards.
     */
    public function area(Area $area): AreaData
    {
        $rows = $this->rows(CardTypes::of($area));

        return $this->areaData($area, $rows[$area->value] ?? []);
    }

    /**
     * Reads the cards of some types, one query per type, grouped by area.
     *
     * @param  list<CardType>  $types
     * @return array<string, list<CardData>>
     */
    private function rows(array $types): array
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $rows = [];

        foreach ($types as $type) {
            foreach ($type->model()::query()->with($type->listRelations())->get() as $card) {
                $rows[$type->area()->value][] = $this->row($card, $today);
            }
        }

        return $rows;
    }

    /**
     * @param  list<CardData>  $rows  the unsorted rows of the area
     */
    private function areaData(Area $area, array $rows): AreaData
    {
        usort($rows, static fn (CardData $a, CardData $b): int => [$a->closed, mb_strtolower($a->label)] <=> [$b->closed, mb_strtolower($b->label)]);

        return new AreaData(
            area: $area->value,
            label: $area->label(),
            count: count(array_filter($rows, static fn (CardData $row): bool => ! $row->closed)),
            overdue: count(array_filter($rows, static fn (CardData $row): bool => $row->overdue)),
            cards: $rows,
        );
    }

    /**
     * The name of the card, from the model.
     *
     * @throws InvalidArgumentException if the model has no `label()`
     */
    private function labelOf(Model $card): string
    {
        return method_exists($card, 'label') ? (string) $card->label() : throw new InvalidArgumentException($card::class.' has no label().');
    }

    /**
     * The nearest date that is today or later; when there is none, the latest
     * past one. Dates that do not expire count here too.
     *
     * @return array{label: string, date: string}|null
     */
    private function nextDate(CardType $type, Model $card, CarbonImmutable $today): ?array
    {
        $coming = null;
        $past = null;

        foreach ($type->dates() as $date) {
            $value = $this->dateOf($card, $date->column);
            if (! $value instanceof CarbonImmutable) {
                continue;
            }
            $found = ['label' => $date->label, 'date' => $value->format('Y-m-d')];
            if ($value >= $today) {
                $coming = $coming === null || $found['date'] < $coming['date'] ? $found : $coming;
            } else {
                $past = $past === null || $found['date'] > $past['date'] ? $found : $past;
            }
        }

        return $coming ?? $past;
    }

    /**
     * Whether one of the deadlines of the card that still count is before today.
     */
    private function hasPassedDeadline(CardType $type, Model $card, CarbonImmutable $today): bool
    {
        foreach ($type->dates() as $date) {
            $value = $this->dateOf($card, $date->column);
            if ($date->countsAsOverdue($card) && $value instanceof CarbonImmutable && $value < $today) {
                return true;
            }
        }

        return false;
    }

    /**
     * A date column as a day, without the time or the zone shifting it.
     */
    private function dateOf(Model $card, string $column): ?CarbonImmutable
    {
        $value = $card->getAttribute($column);

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value->format('Y-m-d'), 'Europe/Rome')->startOfDay();
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, 'Europe/Rome')->startOfDay() : null;
    }

    /**
     * The id of the card as a number.
     *
     * @throws InvalidArgumentException if the card has no numeric id
     */
    private function idOf(Model $card): int
    {
        $id = $card->getKey();

        return is_int($id) ? $id : throw new InvalidArgumentException($card::class.' has no numeric id.');
    }
}
