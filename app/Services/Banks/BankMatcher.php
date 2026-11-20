<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\Banks\BankMatch;
use App\Support\Banks\RuleAccount;
use App\Support\Deadline;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds which planned expense or income a bank transaction pays and which of its due dates it settles. Pure reading:
 * it writes nothing; `SettleFromBank` does the paying. Only transactions in euros are matched.
 */
final readonly class BankMatcher
{
    /** The most days a due date can be from the transaction, whatever the period. */
    private const int MAX_REACH = 45;

    public function __construct(private Deadlines $deadlines, private Occurrences $occurrences) {}

    /**
     * The planned item the transaction pays: among the planned expenses (for money out) or incomes (for money in)
     * that have a rule, have not ended before the day, read the account of the transaction (`RuleAccount`) and whose rule
     * matches the text,
     * the only one, or the one whose planned amount (amount plus fee) is closest to the amount of the transaction.
     * Two at the same distance give none.
     *
     * @param  BankTransaction  $transaction  The transaction.
     */
    public function planned(BankTransaction $transaction): PlannedExpense|PlannedIncome|null
    {
        if ($transaction->currency !== 'EUR' || ! $transaction->booked_on instanceof CarbonImmutable) {
            return null;
        }

        $day = $transaction->booked_on->toDateString();
        $query = $transaction->kind() === 'expense' ? PlannedExpense::query() : PlannedIncome::query();

        /** @var Collection<int, PlannedExpense|PlannedIncome> $candidates */
        $candidates = $query
            ->whereNotNull('bank_match')
            ->where('bank_match', '!=', '')
            ->where(static fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $day))
            ->get()
            ->filter(static fn (PlannedExpense|PlannedIncome $planned): bool => BankMatch::matches((string) $planned->bank_match, (string) $transaction->text)
                && RuleAccount::reads($planned->financial_account_id, $transaction->financial_account_id))
            ->values();

        if ($candidates->count() <= 1) {
            return $candidates->first();
        }

        $cents = $this->cents(abs((float) $transaction->amount));
        $ranked = $candidates
            ->sortBy(fn (PlannedExpense|PlannedIncome $planned): int => abs($this->plannedCents($planned) - $cents))
            ->values();
        $first = $ranked->first();
        $second = $ranked->get(1);

        // The closest wins; two at the same distance cannot be told apart
        if ($first === null || $second === null || abs($this->plannedCents($first) - $cents) === abs($this->plannedCents($second) - $cents)) {
            return null;
        }

        return $first;
    }

    /**
     * The due date the transaction settles: the one closest to the day of the transaction within half a period (at most
     * 45 days; a day more than half is out), paid or not; only when none is within reach, the oldest late one before
     * the day, else none. A paid one comes with its payment: the transaction belongs to it and must not pay an older
     * month (spec 6.6). A skipped due date is never taken. A planned item that does not repeat has only one due date, at
     * any distance, and only while it is unpaid.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item it pays.
     */
    public function deadline(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned): ?Deadline
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $schedule = Schedule::of($planned);

        if (! $schedule->recurring) {
            return $this->deadlines->unpaidUntil([$planned], CarbonImmutable::parse('2999-12-31'), CarbonImmutable::parse('1900-01-01'))[0] ?? null;
        }

        // Half a period, cut to whole days: 15 for a month, 3 for a week
        $reach = min(self::MAX_REACH, (int) floor($this->occurrences->stepDays($schedule) / 2));
        $near = array_values(array_filter(
            $this->deadlines->between([$planned], $day->subDays($reach), $day->addDays($reach)),
            fn (Deadline $deadline): bool => abs($this->days($deadline, $day)) <= $reach,
        ));

        // The list is in order of date, so on a tie the earlier due date stays first
        usort($near, fn (Deadline $a, Deadline $b): int => abs($this->days($a, $day)) <=> abs($this->days($b, $day)));

        if ($near !== []) {
            return $near[0];
        }

        return array_find(
            $this->deadlines->unpaidUntil([$planned], $day, $day),
            static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() < $day->toDateString(),
        );
    }

    /**
     * The whole days from the day of the transaction to a due date, negative when it is before.
     *
     * @param  Deadline  $deadline  The due date.
     * @param  CarbonImmutable  $day  The day of the transaction.
     */
    private function days(Deadline $deadline, CarbonImmutable $day): int
    {
        return (int) round($day->startOfDay()->diffInDays($deadline->occurrence->date->startOfDay(), false));
    }

    /**
     * The planned amount in cents: the amount plus the fee of a planned expense.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    private function plannedCents(PlannedExpense|PlannedIncome $planned): int
    {
        $fee = $planned instanceof PlannedExpense ? (float) $planned->fee_amount : 0.0;

        return $this->cents((float) $planned->amount + $fee);
    }

    /**
     * An amount in cents, rounded so that no float noise reaches a comparison.
     *
     * @param  float  $amount  The amount.
     */
    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
