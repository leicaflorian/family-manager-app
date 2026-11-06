<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Enums\DueMode;
use App\Models\PlannedExpense;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\Deadline;
use App\Support\Money;
use App\Support\Occurrence;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Paid or to pay": whether a bill is offered as to pay, the unpaid due dates a payment receipt can pay, and the
 * three ways a bill to pay reaches the budget. A due date is paid when a movement names its original date
 * (`Deadline::isPaid()`); exceptions and skips are already applied by {@see Deadlines}.
 */
final readonly class DueSearch
{
    public function __construct(
        private Deadlines $deadlines,
        private Occurrences $occurrences,
        private MovementSearch $movements,
    ) {}

    /**
     * Today, as the family lives it: the day in Rome, whatever the time zone of the server.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Europe/Rome');
    }

    /**
     * Whether the reading offers "to pay" by itself: the expense is suggested, the paper is not a receipt or a
     * payment receipt (those are already paid), and the due date is strictly after today.
     *
     * @param  string|null  $kind  the kind of the paper
     * @param  bool  $movementSuggested  whether the expense is suggested
     * @param  CarbonImmutable|null  $dueDate  the due date written on the paper
     * @param  CarbonImmutable  $today  today, see {@see self::today()}
     */
    public static function suggested(?string $kind, bool $movementSuggested, ?CarbonImmutable $dueDate, CarbonImmutable $today): bool
    {
        return $movementSuggested
            && $kind !== 'payment_receipt'
            && $kind !== 'receipt'
            && $dueDate instanceof CarbonImmutable
            && $dueDate->toDateString() > $today->toDateString();
    }

    /**
     * The unpaid due dates a payment receipt can pay: same amount to the cent, within a month of the payment. A
     * planned expense of another issuer is left out; one with no issuer stays. The same issuer comes first, then
     * the closest date, then the lowest id. `others` counts the ones left out of `match`.
     *
     * @param  float  $net  the amount of the receipt
     * @param  CarbonImmutable  $date  the day of the payment
     * @param  int|null  $organizationId  the issuer, if known
     * @param  PlannedExpense|null  $only  the commitment the receipt already has: only its due dates are looked at
     * @return array{match: ?Deadline, others: int}
     */
    public function unpaid(float $net, CarbonImmutable $date, ?int $organizationId, ?PlannedExpense $only = null): array
    {
        /** @var Collection<int, PlannedExpense> $planned */
        $planned = $only instanceof PlannedExpense
            ? PlannedExpense::query()->whereKey($only->id)->whereNotNull('start_date')->get()
            : PlannedExpense::query()->whereNotNull('start_date')->get();

        $planned = $planned->filter(fn (PlannedExpense $row): bool => $organizationId === null
            || $row->organization_id === null
            || $row->organization_id === $organizationId);

        $day = $date->startOfDay();
        $found = collect($this->deadlines->between(
            $planned,
            $day->subDays(MovementSearch::PAYMENT_WINDOW_DAYS),
            $day->addDays(MovementSearch::PAYMENT_WINDOW_DAYS),
        ))
            ->filter(fn (Deadline $deadline): bool => ! $deadline->isPaid()
                && Money::cents($deadline->occurrence->amount) === Money::cents($net))
            ->sort(fn (Deadline $a, Deadline $b): int => [
                (int) ($organizationId === null || $a->planned->organization_id !== $organizationId),
                $this->daysApart($a->occurrence->date, $day),
                $a->planned->id,
            ] <=> [
                (int) ($organizationId === null || $b->planned->organization_id !== $organizationId),
                $this->daysApart($b->occurrence->date, $day),
                $b->planned->id,
            ])
            ->values();

        return ['match' => $found->first(), 'others' => max($found->count() - 1, 0)];
    }

    /**
     * What a bill to pay does with its due date. A commitment that repeats and has a due date at that day: an
     * exception on it (with the original date of the due date). Otherwise a one-off planned expense already there
     * for the same bill: `existing`. Otherwise a new one.
     *
     * @param  PlannedExpense|null  $lead  the commitment the paper belongs to, if any
     * @param  CarbonImmutable  $dueDate  the due date written on the paper
     * @param  float  $amount  the amount to pay
     * @param  int|null  $organizationId  the issuer, if known
     * @return array{mode: DueMode, plan: ?PlannedExpense, occurrence: ?CarbonImmutable}
     */
    public function branch(?PlannedExpense $lead, CarbonImmutable $dueDate, float $amount, ?int $organizationId): array
    {
        if ($lead instanceof PlannedExpense && $lead->is_recurring) {
            $occurrence = $this->occurrences->at(Schedule::of($lead), $dueDate);

            if ($occurrence instanceof Occurrence) {
                return ['mode' => DueMode::Exception, 'plan' => $lead, 'occurrence' => $occurrence->originalDate];
            }
        }

        $similar = $this->movements->plannedSimilar($amount, $dueDate, $organizationId);

        return $similar instanceof PlannedExpense
            ? ['mode' => DueMode::Existing, 'plan' => $similar, 'occurrence' => null]
            : ['mode' => DueMode::New, 'plan' => null, 'occurrence' => null];
    }

    /**
     * Whole days between two dates, whichever comes first.
     *
     * @param  CarbonImmutable  $a  a date
     * @param  CarbonImmutable  $b  another date
     */
    private function daysApart(CarbonImmutable $a, CarbonImmutable $b): int
    {
        return (int) abs($a->startOfDay()->diffInDays($b->startOfDay()));
    }
}
