<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PlannedExpenseException;
use App\Models\PlannedIncomeException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An exception of a planned expense or income: one due date that is skipped, has another amount or another date.
 */
#[TypeScript]
final class ExceptionData extends Data
{
    /**
     * @param  int  $id  the id of the exception
     * @param  string  $occurrence_date  the day the rules give for the due date it changes, ISO: it identifies the due date
     * @param  string  $type  `skip`, `amount_override` or `date_override`
     * @param  float|null  $amount_override  the other amount
     * @param  string|null  $date_override  the other day, ISO
     * @param  string|null  $notes  the note
     * @param  DeadlineData|null  $deadline  the due date it changes, skipped ones included, which *Change this due date* opens; null when the day is no longer a due date
     */
    public function __construct(
        public readonly int $id,
        public readonly string $occurrence_date,
        public readonly string $type,
        public readonly ?float $amount_override,
        public readonly ?string $date_override,
        public readonly ?string $notes,
        public readonly ?DeadlineData $deadline = null,
    ) {}

    /**
     * The data of an exception.
     *
     * @param  PlannedExpenseException|PlannedIncomeException  $exception  the exception
     * @param  DeadlineData|null  $deadline  the due date it changes
     */
    public static function fromException(PlannedExpenseException|PlannedIncomeException $exception, ?DeadlineData $deadline = null): self
    {
        return new self(
            id: $exception->id,
            occurrence_date: $exception->occurrence_date?->toDateString() ?? '',
            type: $exception->type->value,
            amount_override: $exception->amount_override === null ? null : round((float) $exception->amount_override, 2),
            date_override: $exception->date_override?->toDateString(),
            notes: $exception->notes,
            deadline: $deadline,
        );
    }
}
