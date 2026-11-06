<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\Document;
use App\Models\Expense;
use App\Models\PlannedExpense;
use App\Services\Occurrences;
use App\Support\Schedule;
use Carbon\CarbonImmutable;

/**
 * Moves the documents waiting on a planned expense to the expense that pays it. Shared by *I paid it* and the import.
 */
final readonly class MovePlannedDocuments
{
    public function __construct(private Occurrences $occurrences) {}

    /**
     * Links to the expense the documents of the planned expense that have no expense yet: all of them for a one-off
     * planned expense, those from one period before the due date to half a period after for a recurring one.
     *
     * @param  PlannedExpense  $planned  The planned expense.
     * @param  CarbonImmutable  $occurrenceDate  The due date being paid.
     * @param  Expense  $expense  The expense just created.
     */
    public function handle(PlannedExpense $planned, CarbonImmutable $occurrenceDate, Expense $expense): void
    {
        $query = Document::query()->where('planned_expense_id', $planned->id)->whereDoesntHave('expenses');
        $schedule = Schedule::of($planned);

        if ($schedule->recurring) {
            $step = $this->occurrences->stepDays($schedule);
            $due = $occurrenceDate->startOfDay();
            $query->whereBetween('date', [$due->subDays((int) round($step)), $due->addDays((int) floor($step / 2))]);
        }

        $expense->documents()->syncWithoutDetaching($query->pluck('id')->all());
    }
}
