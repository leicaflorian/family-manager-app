<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Enums\ExceptionType;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * *Change this due date*: rewrites the exceptions of one due date of a planned expense or income,
 * without touching the planned item.
 */
final readonly class ChangeOccurrence
{
    /**
     * Replaces the exceptions of one due date with the chosen ones, one row per choice, in one transaction.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item the due date belongs to.
     * @param  CarbonImmutable  $originalDate  The original date of the due date: the key of its exceptions.
     * @param  bool  $skip  The due date disappears; the amount and the date are ignored.
     * @param  ?float  $amount  Another amount, null when not chosen.
     * @param  ?CarbonImmutable  $date  Another date, null when not chosen.
     * @param  ?string  $notes  The note, the same on every row.
     */
    public function handle(PlannedExpense|PlannedIncome $planned, CarbonImmutable $originalDate, bool $skip, ?float $amount, ?CarbonImmutable $date, ?string $notes): void
    {
        DB::transaction(function () use ($planned, $originalDate, $skip, $amount, $date, $notes): void {
            $planned->exceptions()->whereDate('occurrence_date', $originalDate->toDateString())->delete();

            $rows = match (true) {
                $skip => [['type' => ExceptionType::Skip]],
                default => array_values(array_filter([
                    $amount === null ? null : ['type' => ExceptionType::AmountOverride, 'amount_override' => $amount],
                    $date instanceof CarbonImmutable ? ['type' => ExceptionType::DateOverride, 'date_override' => $date] : null,
                ])),
            };

            foreach ($rows as $row) {
                $planned->exceptions()->create([...$row, 'occurrence_date' => $originalDate, 'notes' => $notes]);
            }
        });
    }
}
