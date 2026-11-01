<?php

declare(strict_types=1);

namespace App\Cards;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of deadline of a card or a document: the date column of a model that
 * the Agenda, the Home and the reminders read, with the statuses in which the
 * date still counts.
 */
final readonly class DeadlineSource
{
    /**
     * @param  string  $field  the key, the same as `reminders.source_field` of Directus (`vehicles.road_tax_due_date`)
     * @param  class-string<Model>  $model  the model that holds the date
     * @param  string  $column  the date column
     * @param  list<BackedEnum>  $activeStatuses  the statuses in which the date still counts; empty when every status does
     * @param  (Closure(Builder<Model>, CarbonImmutable): void)|null  $condition  an extra condition on the query, given today; null when none is needed
     * @param  string|null  $label  what expires, in English; null when the row shows the title of the document
     * @param  string|null  $personColumn  the column of the person it belongs to; null when it is nobody's
     * @param  bool  $hasExpense  whether a planned expense of the card pays this deadline (one row in the Agenda)
     * @param  string  $title  what to do, in English (translated with `__()` where it is shown): the title of the reminder
     * @param  bool  $overdue  whether the reminder is wanted also once the date has passed, because the date moves only when confirmed (the renewal of a subscription)
     */
    public function __construct(
        public string $field,
        public string $model,
        public string $column,
        public array $activeStatuses = [],
        public ?Closure $condition = null,
        public ?string $label = null,
        public ?string $personColumn = null,
        public bool $hasExpense = false,
        public string $title = '',
        public bool $overdue = false,
    ) {}

    /**
     * The cards of the current family that still have this deadline: in an
     * active status, with the extra condition and a date.
     *
     * @param  CarbonImmutable  $today  today in Rome, as the caller has it
     * @return Builder<Model>
     */
    public function query(CarbonImmutable $today): Builder
    {
        $query = $this->model::query()->whereNotNull($this->column);

        if ($this->activeStatuses !== []) {
            $query->whereIn('status', array_map(static fn (BackedEnum $status): string|int => $status->value, $this->activeStatuses));
        }

        if ($this->condition instanceof Closure) {
            ($this->condition)($query, $today);
        }

        return $query;
    }

    /**
     * The date of a card while it still has this deadline: the date is not
     * empty, the status is an active one and the condition holds, whatever the
     * date. It follows the rules of `query()` on the one card.
     *
     * @param  Model  $card  a card of the model of this kind
     * @param  CarbonImmutable  $today  today in Rome, as the caller has it
     * @return CarbonImmutable|null the date, or null when the card has no such deadline
     */
    public function current(Model $card, CarbonImmutable $today): ?CarbonImmutable
    {
        $value = $card->getAttributes()[$this->column] ?? null;

        if ($value === null || ! $this->query($today)->whereKey($card->getKey())->exists()) {
            return null;
        }

        return CarbonImmutable::parse($value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr(is_string($value) ? $value : '', 0, 10), $today->getTimezone());
    }

    /**
     * The date of a card when it wants a reminder: the card has the deadline
     * (see `current()`) and the date is today or later (or any date, for a
     * kind that is overdue).
     *
     * @param  Model  $card  a card of the model of this kind
     * @param  CarbonImmutable  $today  today in Rome, as the caller has it
     * @return CarbonImmutable|null the date, or null when the card wants no reminder
     */
    public function wants(Model $card, CarbonImmutable $today): ?CarbonImmutable
    {
        $date = $this->current($card, $today);

        return $date instanceof CarbonImmutable && ($this->overdue || $date->greaterThanOrEqualTo($today->startOfDay())) ? $date : null;
    }
}
