<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Support\LoanFigures;
use App\Support\LoanTerms;
use App\Support\ScheduleException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * What a loan works out by itself: the date of the last instalment, the number of instalments, the total to pay
 * and the cost, how far the payments are, and the planned expense of the instalment with the exception of the
 * final balloon payment. Pure calculation, no database. It replaces `rules.ts` of the Directus loan-automation.
 */
final class LoanSchedule
{
    /** The most instalments read from one loan, so a wrong count can never loop for ever. {@see RefreshLoan} keeps to it too. */
    public const int MAX_INSTALLMENTS = 1200;

    /**
     * Loan column to the column of its planned expense, for the values that follow the loan after the creation.
     *
     * @var array<string, string>
     */
    private const array FOLLOWED = [
        'name' => 'title',
        'installment_amount' => 'amount',
        'start_date' => 'start_date',
        'end_date' => 'end_date',
        'organization_id' => 'organization_id',
        'financial_account_id' => 'financial_account_id',
        'person_id' => 'person_id',
    ];

    /** The planned expense needs these: a value emptied on the loan leaves them as they are. */
    private const array KEPT_WHEN_EMPTY = ['title', 'amount', 'start_date', 'financial_account_id'];

    /**
     * Monthly instalments from `$start` whose date is not after `$end`: 15/11 to 15/10 of the next year gives 12.
     *
     * @param  CarbonImmutable  $start  The first instalment.
     * @param  CarbonImmutable  $end  The last day.
     * @return ?int Null when the end is before the start.
     */
    public function installmentsBetween(CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        if ($end->toDateString() < $start->toDateString()) {
            return null;
        }

        $months = ($end->year - $start->year) * 12 + $end->month - $start->month;

        return $this->addMonths($start, $months)->toDateString() <= $end->toDateString() ? $months + 1 : $months;
    }

    /**
     * The instalments of the loan, one a month from the start date; the last one is the final balloon payment when
     * the loan has one. The exceptions of the planned expenses linked to the loan apply to the instalment of the
     * same date: a moved payment counts on its new date, a different amount replaces the instalment, a skipped one
     * is still to pay (date null).
     *
     * @param  LoanTerms  $terms  The loan.
     * @param  list<ScheduleException>  $exceptions  The exceptions of its planned expenses.
     * @return list<array{date: ?CarbonImmutable, amount: ?float}>
     */
    public function installments(LoanTerms $terms, array $exceptions): array
    {
        $count = min($terms->count ?? 0, self::MAX_INSTALLMENTS);

        if (! $terms->start instanceof CarbonImmutable || $count < 1) {
            return [];
        }

        $byDate = [];
        foreach ($exceptions as $exception) {
            $byDate[$exception->occurrenceDate->toDateString()] = $exception;
        }

        $list = [];
        for ($k = 0; $k < $count; $k++) {
            $original = $this->addMonths($terms->start, $k);
            $exception = $byDate[$original->toDateString()] ?? null;
            $date = $original;
            $amount = $k === $count - 1 && $terms->finalInstallment !== null ? $terms->finalInstallment : $terms->installment;

            if ($exception instanceof ScheduleException) {
                match ($exception->type) {
                    ExceptionType::Skip => $date = null,
                    ExceptionType::DateOverride => $date = $exception->date ?? $date,
                    ExceptionType::AmountOverride => $amount = $exception->amount ?? $amount,
                };
            }

            $list[] = ['date' => $date, 'amount' => $amount];
        }

        return $list;
    }

    /**
     * The figures of the loan:
     * - the date of the last instalment, when empty: start plus (instalments - 1) months; one written stays;
     * - the number of instalments, when empty and both dates are there;
     * - the total to pay (the sum of the instalments with their exceptions; without a start date the instalment by
     *   the count, the balloon payment last) and the cost (the total less the amount financed);
     * - instalments paid (date not after today), left and what is left to pay. A paid off loan has nothing left.
     *
     * @param  LoanTerms  $terms  The loan.
     * @param  list<ScheduleException>  $exceptions  The exceptions of its planned expenses.
     * @param  CarbonImmutable  $today  The day the progress is counted on.
     */
    public function derive(LoanTerms $terms, array $exceptions, CarbonImmutable $today): LoanFigures
    {
        $count = $terms->count;
        $end = $terms->end;

        if ($terms->start instanceof CarbonImmutable && $count !== null && ! $end instanceof CarbonImmutable) {
            $end = $this->addMonths($terms->start, min($count, self::MAX_INSTALLMENTS) - 1);
        } elseif ($terms->start instanceof CarbonImmutable && $end instanceof CarbonImmutable && $count === null) {
            $count = $this->installmentsBetween($terms->start, $end);
            $count = $count === 0 ? null : $count;
        }

        $terms = $terms->withCount($count);
        $list = $this->installments($terms, $exceptions);
        [$total, $cost] = $this->totals($terms, $list);

        if ($list === []) {
            return new LoanFigures($end, $count, $total, $cost, null, null, null);
        }

        if ($terms->status === LoanStatus::PaidOff) {
            return new LoanFigures($end, $count, $total, $cost, count($list), 0, 0.0);
        }

        $todayDay = $today->toDateString();
        $left = array_values(array_filter($list, fn (array $i): bool => $i['date'] === null || $i['date']->toDateString() > $todayDay));
        $amounts = array_column($left, 'amount');
        $residual = in_array(null, $amounts, true) ? null : $this->cents(array_sum($amounts));

        return new LoanFigures($end, $count, $total, $cost, count($list) - count($left), count($left), $residual);
    }

    /**
     * The planned expense of the instalment, for a loan that has none: every month from the start date to the last
     * instalment. The final balloon payment is its exception on the last date, see {@see self::balloon()}.
     *
     * @param  Loan  $loan  The loan.
     * @param  ?int  $categoryId  The category of the loan expenses.
     * @param  ?int  $defaultAccountId  The account given when the loan has none.
     * @return ?array<string, mixed> The columns of `planned_expenses`; null when the loan is not active or has no instalment amount or start date.
     */
    public function plannedExpense(Loan $loan, ?int $categoryId, ?int $defaultAccountId): ?array
    {
        $terms = LoanTerms::of($loan);

        if ($terms->status !== LoanStatus::Active || ! $terms->start instanceof CarbonImmutable || $terms->installment === null) {
            return null;
        }

        return [
            'title' => $loan->name,
            'amount' => $terms->installment,
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => IntervalUnit::Month->value,
            'start_date' => $terms->start->toDateString(),
            'end_date' => $terms->end?->toDateString(),
            'card_type' => $loan->getMorphClass(),
            'card_id' => $loan->id,
            'is_auto_created' => true,
            'category_id' => $categoryId,
            'organization_id' => $loan->organization_id,
            'financial_account_id' => $loan->financial_account_id ?? $defaultAccountId,
            'person_id' => $loan->person_id,
        ];
    }

    /**
     * The exception that puts the final balloon payment on the last date.
     *
     * @param  LoanTerms  $terms  The loan.
     * @return ?array{occurrence_date: string, amount_override: float} Null when the loan has none.
     */
    public function balloon(LoanTerms $terms): ?array
    {
        if ($terms->finalInstallment === null || ! $terms->start instanceof CarbonImmutable || ! $terms->end instanceof CarbonImmutable
            || $terms->end->toDateString() <= $terms->start->toDateString()) {
            return null;
        }

        return ['occurrence_date' => $terms->end->toDateString(), 'amount_override' => $terms->finalInstallment];
    }

    /**
     * What to change on the planned expense created for the loan, after the loan went from `$before` to `$after`:
     * - a value changed on the loan goes on the planned expense when it still had the old one: a value corrected
     *   by hand on the planned expense stays. A loan without an account had given the default one;
     * - a loan paid off before the last instalment ends the planned expense today.
     *
     * @param  array<string, mixed>  $before  The columns of the loan before.
     * @param  array<string, mixed>  $after  The columns of the loan after.
     * @param  PlannedExpense  $expense  The planned expense of the loan.
     * @param  CarbonImmutable  $today  The day a paid off loan ends the expense.
     * @param  ?int  $defaultAccountId  The account a loan without one had given.
     * @return array<string, mixed> The columns of `planned_expenses` to write.
     */
    public function expenseChanges(array $before, array $after, PlannedExpense $expense, CarbonImmutable $today, ?int $defaultAccountId): array
    {
        $patch = [];

        foreach (self::FOLLOWED as $from => $to) {
            $old = $before[$from] ?? null;
            $old = $from === 'financial_account_id' ? $old ?? $defaultAccountId : $old;
            $new = $after[$from] ?? null;

            if ($this->equal($old, $new) || ! $this->equal($expense->getAttribute($to), $old)) {
                continue;
            }

            if ($new === null && in_array($to, self::KEPT_WHEN_EMPTY, true)) {
                continue;
            }

            $patch[$to] = str_ends_with($to, '_date') ? $this->dayText($new) : $new;
        }

        if ($this->status($before['status'] ?? null) === LoanStatus::Active && $this->status($after['status'] ?? null) === LoanStatus::PaidOff) {
            $end = $this->dayText(array_key_exists('end_date', $patch) ? $patch['end_date'] : $expense->end_date);

            if ($end === null || $end > $today->toDateString()) {
                $patch['end_date'] = $today->toDateString();
            }
        }

        return $patch;
    }

    /**
     * What to do with the exception of the final balloon payment when the last date or its amount changed on the
     * loan: the one written by the system (same date and amount as before) moves, changes or goes; one changed by
     * hand stays. A balloon payment added later gets its exception, unless that date already has one.
     *
     * @param  LoanTerms  $before  The loan before.
     * @param  LoanTerms  $after  The loan after.
     * @param  Collection<int, PlannedExpenseException>  $exceptions  The exceptions of the planned expense.
     * @return array{update?: array{0: int, 1: array<string, mixed>}, remove?: int, create?: array<string, mixed>}
     */
    public function balloonChanges(LoanTerms $before, LoanTerms $after, Collection $exceptions): array
    {
        $old = $this->balloon($before);
        $wanted = $this->balloon($after);

        if ($this->equal($old['occurrence_date'] ?? null, $wanted['occurrence_date'] ?? null)
            && $this->equal($old['amount_override'] ?? null, $wanted['amount_override'] ?? null)) {
            return [];
        }

        $on = fn (string $date): ?PlannedExpenseException => $exceptions->first(
            fn (PlannedExpenseException $e): bool => $e->occurrence_date?->toDateString() === $date,
        );

        if ($old === null) {
            return $wanted !== null && ! $on($wanted['occurrence_date']) instanceof PlannedExpenseException ? ['create' => $wanted] : [];
        }

        $written = $on($old['occurrence_date']);

        if (! $written instanceof PlannedExpenseException || $written->type !== ExceptionType::AmountOverride
            || ! $this->equal($written->amount_override, $old['amount_override'])) {
            return [];
        }

        if ($wanted === null) {
            return ['remove' => $written->id];
        }

        // The new last date may already have an exception written by hand: that one stays, ours goes.
        $taken = $on($wanted['occurrence_date']);

        if ($taken instanceof PlannedExpenseException && $taken->id !== $written->id) {
            return ['remove' => $written->id];
        }

        return ['update' => [$written->id, $wanted]];
    }

    /**
     * The total to pay and the cost. The total is the sum of the instalments of `$list`, so an instalment with a
     * different amount counts for what it is; without the list (no start date) it is the instalment by the count,
     * the balloon payment as the last. Null without the instalment or the count. The cost needs the amount financed.
     *
     * @param  LoanTerms  $terms  The loan.
     * @param  list<array{date: ?CarbonImmutable, amount: ?float}>  $list  Its instalments.
     * @return array{0: ?float, 1: ?float} The total to pay and the cost.
     */
    private function totals(LoanTerms $terms, array $list): array
    {
        $count = min($terms->count ?? 0, self::MAX_INSTALLMENTS);

        if ($terms->installment === null || $count < 1) {
            return [null, null];
        }

        $total = $this->cents(match (true) {
            $list !== [] => array_sum(array_column($list, 'amount')),
            $terms->finalInstallment !== null => $terms->installment * ($count - 1) + $terms->finalInstallment,
            default => $terms->installment * $count,
        });

        return [$total, $terms->financed !== null ? $this->cents($total - $terms->financed) : null];
    }

    /**
     * Adds months keeping the day, the last of the month in short months.
     *
     * @param  CarbonImmutable  $date  The starting day.
     * @param  int  $months  The months to add.
     */
    private function addMonths(CarbonImmutable $date, int $months): CarbonImmutable
    {
        return resolve(Occurrences::class)->addMonths($date, $months);
    }

    /**
     * Rounds to the cent.
     *
     * @param  float|int  $value  The amount.
     */
    private function cents(float|int $value): float
    {
        return round($value * 100) / 100;
    }

    /**
     * The status as the enum, from the enum or its text.
     *
     * @param  mixed  $value  The status.
     */
    private function status(mixed $value): ?LoanStatus
    {
        return $value instanceof LoanStatus ? $value : (is_string($value) ? LoanStatus::tryFrom($value) : null);
    }

    /**
     * The day `Y-m-d` of a date or of text starting with one; null when empty.
     *
     * @param  mixed  $value  The date.
     */
    private function dayText(mixed $value): ?string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_string($value) && $value !== '' => substr($value, 0, 10),
            default => null,
        };
    }

    /**
     * Whether two values are the same: empty is only equal to empty, dates by their day, numbers by value.
     *
     * @param  mixed  $a  A value.
     * @param  mixed  $b  Another value.
     */
    private function equal(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        $x = $a instanceof DateTimeInterface ? $a->format('Y-m-d') : $a;
        $y = $b instanceof DateTimeInterface ? $b->format('Y-m-d') : $b;

        if (! is_scalar($x) || ! is_scalar($y)) {
            return false;
        }

        [$x, $y] = [(string) $x, (string) $y];
        $isDay = fn (string $text): bool => preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1;

        if ($isDay($x) && $isDay($y)) {
            return strncmp($x, $y, 10) === 0;
        }

        return $x === $y || (trim($x) !== '' && trim($y) !== '' && is_numeric($x) && is_numeric($y) && (float) $x === (float) $y);
    }
}
