<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Services\Occurrences;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankText;
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

    public function __construct(private Deadlines $deadlines, private Occurrences $occurrences, private BankDuplicates $duplicates, private BankFees $fees) {}

    /**
     * The planned item the transaction pays: among the planned expenses (for money out) or incomes (for money in)
     * that have a rule, have not ended before the day, read the account of the transaction (`RuleAccount`) and whose rule
     * matches the text (never for the commission of a direct debit),
     * the only one, or the one whose planned amount (amount plus fee) is closest to the amount of the transaction (a
     * direct debit with its commission counts as the two together; a transaction without a commission is compared with
     * the amount without the fee too, since the commission may come apart). Two at the same distance give none.
     *
     * @param  BankTransaction  $transaction  The transaction.
     */
    public function planned(BankTransaction $transaction): PlannedExpense|PlannedIncome|null
    {
        // A commission of a direct debit never pays a due date: it joins the expense of its debit (`AttachBankFee`)
        if ($transaction->currency !== 'EUR' || ! $transaction->booked_on instanceof CarbonImmutable || BankText::isFee($transaction->text)) {
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

        $amounts = $this->fees->amounts($transaction);
        // The amounts differ from the transaction alone only when its commission was added
        $withFee = $amounts !== [$this->cents(abs((float) $transaction->amount))];
        $distance = fn (PlannedExpense|PlannedIncome $planned): int => $this->distance($planned, $amounts, $withFee);
        $ranked = $candidates->sortBy($distance)->values();
        $first = $ranked->first();
        $second = $ranked->get(1);

        // The closest wins; two at the same distance cannot be told apart
        if ($first === null || $second === null || $distance($first) === $distance($second)) {
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
            return $this->deadlines->unpaidUntil([$planned], CarbonImmutable::parse('2999-12-31'), CarbonImmutable::parse('1900-01-01'), withWaived: true)[0] ?? null;
        }

        // Half a period, cut to whole days: 15 for a month, 3 for a week
        $reach = $this->reach($schedule);
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
            $this->deadlines->unpaidUntil([$planned], $day, $day, withWaived: true),
            static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() < $day->toDateString(),
        );
    }

    /**
     * The due date a person who looks for the planned item by hand would pay, at any distance: the one of `deadline()`
     * when it is within reach and open, or paid by hand with a payment no other bank transaction has (the transaction
     * links to it); otherwise the open due date closest to the day of the transaction, before or after it, at most
     * 12 months back and 24 ahead of that day. An item that does not repeat has one due date: open, or paid by hand
     * with a free payment. A skipped due date is never taken.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    public function deadlineAnywhere(BankTransaction $transaction, PlannedExpense|PlannedIncome $planned): ?Deadline
    {
        $day = $transaction->booked_on;

        if (! $day instanceof CarbonImmutable) {
            return null;
        }

        $schedule = Schedule::of($planned);

        if (! $schedule->recurring) {
            $only = $this->deadlines->between([$planned], CarbonImmutable::parse('1900-01-01'), CarbonImmutable::parse('2999-12-31'))[0] ?? null;

            return $only instanceof Deadline && $this->usable($only, $transaction) ? $only : null;
        }

        $near = $this->deadline($transaction, $planned);

        // A paid one comes only from within reach; an open one counts as near only within reach, else the closest wins
        if ($near instanceof Deadline && ! $near->isSkipped() && $this->usable($near, $transaction)
            && ($near->isPaid() || abs($this->days($near, $day)) <= $this->reach($schedule))) {
            return $near;
        }

        $open = array_values(array_filter(
            $this->deadlines->unpaidUntil([$planned], $day->addMonthsNoOverflow(24), $day, withWaived: true),
            static fn (Deadline $deadline): bool => ! $deadline->isSkipped(),
        ));

        // In order of date: on a tie the earlier due date stays first
        usort($open, fn (Deadline $a, Deadline $b): int => abs($this->days($a, $day)) <=> abs($this->days($b, $day)));

        return $open[0] ?? null;
    }

    /**
     * Whether a due date can be offered: open, or paid by a payment no other bank transaction has.
     *
     * @param  Deadline  $deadline  The due date.
     * @param  BankTransaction  $transaction  The transaction.
     */
    private function usable(Deadline $deadline, BankTransaction $transaction): bool
    {
        return ! $deadline->isPaid() || ($deadline->payment !== null && $this->duplicates->isFree($deadline->payment, $transaction));
    }

    /**
     * How many days a due date can be from the transaction to count as near: half a period, at most 45.
     *
     * @param  Schedule  $schedule  The recurrence.
     */
    private function reach(Schedule $schedule): int
    {
        return min(self::MAX_REACH, (int) floor($this->occurrences->stepDays($schedule) / 2));
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
     * How far, in cents, the planned amounts of an item are from the closest amount of the transaction.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  list<int>  $amounts  The amounts of the transaction (`BankFees::amounts()`).
     * @param  bool  $withFee  Whether the transaction comes with its commission.
     */
    private function distance(PlannedExpense|PlannedIncome $planned, array $amounts, bool $withFee): int
    {
        $gaps = [];

        foreach ($this->plannedCents($planned, $withFee) as $expected) {
            foreach ($amounts as $cents) {
                $gaps[] = abs($expected - $cents);
            }
        }

        return $gaps === [] ? PHP_INT_MAX : min($gaps);
    }

    /**
     * The planned amounts in cents: the amount plus the fee of a planned expense, and also the amount alone when the
     * transaction has no commission with it (the commission of a direct debit may come apart, later).
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  bool  $withFee  Whether the transaction comes with its commission.
     * @return non-empty-list<int>
     */
    private function plannedCents(PlannedExpense|PlannedIncome $planned, bool $withFee): array
    {
        $fee = $planned instanceof PlannedExpense ? $this->cents((float) $planned->fee_amount) : 0;
        $amount = $this->cents((float) $planned->amount);

        return $fee > 0 && ! $withFee ? [$amount + $fee, $amount] : [$amount + $fee];
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
