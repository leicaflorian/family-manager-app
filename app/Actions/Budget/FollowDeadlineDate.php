<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Cards\DeadlineSources;
use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Models\PlannedExpense;
use App\Services\Deadlines;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * When the road tax or inspection date of a card changes, the planned expense that pays it follows: its next unpaid
 * due date moves to the new date (an exception, so the payments stay where they are) or, when it has no payments yet
 * and the new date comes before its start, its start moves. The date is kept by the person in the card: a payment
 * never moves it. It works in the current family only.
 */
final readonly class FollowDeadlineDate
{
    /**
     * @param  Deadlines  $deadlines  The due dates of a planned expense.
     * @param  SavePlanned  $savePlanned  Saves a changed start, with the remap of payments and exceptions.
     * @param  ChangeOccurrence  $changeOccurrence  Moves one due date.
     */
    public function __construct(private Deadlines $deadlines, private SavePlanned $savePlanned, private ChangeOccurrence $changeOccurrence) {}

    /**
     * Follows every changed, non-empty date of the card that a planned expense of its own pays.
     *
     * @param  Model  $card  The card, just saved.
     */
    public function handle(Model $card): void
    {
        foreach (DeadlineSources::ownExpenseFor($card::class) as $source) {
            $date = $card->getAttribute($source->column);

            if (! $card->wasChanged($source->column) || ! $date instanceof CarbonInterface) {
                continue;
            }

            $planned = PlannedExpense::query()
                ->where('card_type', $card->getMorphClass())
                ->where('card_id', $card->getKey())
                ->where('deadline_field', $source->field)
                ->where(static fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $date->toDateString()))
                ->latest('start_date')
                ->first();

            if ($planned instanceof PlannedExpense) {
                $this->follow($planned, CarbonImmutable::parse($date->toDateString()));
            }
        }
    }

    /**
     * Moves the planned expense to the new date of the card.
     *
     * @param  PlannedExpense  $planned  The planned expense that pays the deadline.
     * @param  CarbonImmutable  $date  The new date of the card.
     */
    private function follow(PlannedExpense $planned, CarbonImmutable $date): void
    {
        // A one-off has a single due date: it goes where the card says, whatever the distance
        $oneOff = ! $planned->is_recurring && $planned->start_date !== null && ! $date->isSameDay($planned->start_date);

        if (($oneOff || ($planned->start_date !== null && $date->lt($planned->start_date))) && ! $planned->expenses()->exists()) {
            $this->savePlanned->handle('expense', ['start_date' => $date], $planned);

            return;
        }

        $reach = $this->reach($planned);
        $next = array_find(
            $this->deadlines->between([$planned], $date->subDays($reach), $date->addDays($reach)),
            static fn (Deadline $deadline): bool => $deadline->isDue(),
        );

        if (! $next instanceof Deadline || $next->occurrence->date->isSameDay($date)) {
            return;
        }

        $original = $next->occurrence->originalDate;
        // Changing the date rewrites the exceptions of the due date, so the amount chosen for it is passed again
        $amount = $planned->exceptions()->whereDate('occurrence_date', $original->toDateString())->where('type', ExceptionType::AmountOverride)->value('amount_override');

        $this->changeOccurrence->handle($planned, $original, false, is_numeric($amount) ? (float) $amount : null, $date, $next->occurrence->notes);
    }

    /**
     * How many days around the new date a due date still counts as the one that follows it: half the interval.
     *
     * @param  PlannedExpense  $planned  The planned expense.
     */
    private function reach(PlannedExpense $planned): int
    {
        $days = match ($planned->interval_unit) {
            IntervalUnit::Week => 7,
            IntervalUnit::Month => 30,
            IntervalUnit::Year => 365,
        };

        return max(1, intdiv($days * max(1, (int) $planned->interval), 2));
    }
}
