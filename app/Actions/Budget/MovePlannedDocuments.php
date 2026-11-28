<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\Document;
use App\Models\Expense;
use App\Models\PlannedExpense;
use App\Services\Occurrences;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Moves the documents waiting on a planned expense to the expense that pays it. Shared by *I paid it* and the import.
 */
final readonly class MovePlannedDocuments
{
    public function __construct(private Occurrences $occurrences) {}

    /**
     * Links to the expense the documents of the planned expense that have no expense yet (`waiting`).
     *
     * @param  PlannedExpense  $planned  The planned expense.
     * @param  CarbonImmutable  $occurrenceDate  The due date being paid.
     * @param  Expense  $expense  The expense just created.
     */
    public function handle(PlannedExpense $planned, CarbonImmutable $occurrenceDate, Expense $expense): void
    {
        $expense->documents()->syncWithoutDetaching($this->waiting($planned, $occurrenceDate)->pluck('id')->all());
    }

    /**
     * The documents waiting on the planned expense that paying the due date moves to the expense: all of them for a
     * one-off planned expense, those from one period before the due date to half a period after for a recurring one,
     * and only those with no expense yet. A document that waits on more planned expenses (a bill of electricity and
     * gas) already has the expense of the other one, so it counts as paid only when it has an expense of this planned
     * expense; any other expense linked to it, even one linked by hand without a planned expense, does not keep it
     * from moving. The bank reads the same documents to tell one debit that pays two planned expenses.
     *
     * @param  PlannedExpense  $planned  The planned expense.
     * @param  CarbonImmutable  $occurrenceDate  The due date being paid.
     * @return Builder<Document>
     */
    public function waiting(PlannedExpense $planned, CarbonImmutable $occurrenceDate): Builder
    {
        $query = Document::query()->waitingFor([$planned->id])->where(static function (Builder $query) use ($planned): void {
            $query->whereDoesntHave('expenses')->orWhere(static function (Builder $query) use ($planned): void {
                $query->has('waitingPlans')->whereDoesntHave('expenses', static function (Builder $query) use ($planned): void {
                    $query->where('expenses.planned_expense_id', $planned->id);
                });
            });
        });
        $schedule = Schedule::of($planned);

        if ($schedule->recurring) {
            $step = $this->occurrences->stepDays($schedule);
            $due = $occurrenceDate->startOfDay();
            $query->whereBetween('date', [$due->subDays((int) round($step)), $due->addDays((int) floor($step / 2))]);
        }

        return $query;
    }
}
