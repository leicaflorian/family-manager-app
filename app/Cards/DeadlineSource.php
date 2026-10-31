<?php

declare(strict_types=1);

namespace App\Cards;

use BackedEnum;
use Closure;
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
     * @param  (Closure(Builder<Model>): void)|null  $condition  an extra condition on the query; null when none is needed
     * @param  string|null  $label  what expires, in English; null when the row shows the title of the document
     * @param  string|null  $personColumn  the column of the person it belongs to; null when it is nobody's
     * @param  bool  $hasExpense  whether a planned expense of the card pays this deadline (one row in the Agenda)
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
    ) {}

    /**
     * The cards of the current family that still have this deadline: in an
     * active status, with the extra condition and a date.
     *
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $query = $this->model::query()->whereNotNull($this->column);

        if ($this->activeStatuses !== []) {
            $query->whereIn('status', array_map(static fn (BackedEnum $status): string|int => $status->value, $this->activeStatuses));
        }

        if ($this->condition instanceof Closure) {
            ($this->condition)($query);
        }

        return $query;
    }
}
