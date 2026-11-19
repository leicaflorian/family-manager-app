<?php

declare(strict_types=1);

namespace App\Observers;

use App\Cards\DeadlineSources;
use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Support\ModelId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment of a planned expense of a card pays the deadline of that card due on the same day (the kinds of
 * deadline with an expense: first and last instalment, road tax, inspection, policy renewal): its open automatic
 * reminder is marked as done, as the Agenda already leaves the deadline out once the due date is paid.
 */
final class ExpenseDeadlineObserver
{
    /**
     * An expense was created or changed. Only a new one, or one that changed the due date it pays, can pay a deadline.
     *
     * @param  Expense  $expense  the expense
     */
    public function saved(Expense $expense): void
    {
        if (! $expense->wasRecentlyCreated && ! $expense->wasChanged(['planned_expense_id', 'occurrence_date'])) {
            return;
        }

        $planned = $expense->plannedExpense;
        $day = $expense->occurrence_date;
        $card = $planned instanceof PlannedExpense ? $planned->card : null;

        if (! $card instanceof Model || ! $day instanceof CarbonImmutable) {
            return;
        }

        foreach (DeadlineSources::forModel($card::class) as $source) {
            if (! $source->hasExpense) {
                continue;
            }

            $reminders = Reminder::query()
                ->automaticFor($source->field, $card->getMorphClass(), [ModelId::of($card)])
                ->open()
                ->whereDate('reminders.due_date', $day->toDateString())
                ->get();

            foreach ($reminders as $reminder) {
                $reminder->update(['status' => ReminderStatus::Done]);
            }
        }
    }
}
