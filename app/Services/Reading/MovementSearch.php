<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Models\Employment;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Money;
use App\Support\Reading\IssuerOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The searches that stop a reading from creating something twice, or find what it settles: the commitment a paper
 * belongs to, the movement already recorded for it, the expense a payment receipt pays, the one-off planned expense
 * already there. Every query goes through the family scope of the models.
 *
 * Kinds are `expense` and `income`; amounts are floats compared in cents.
 */
final class MovementSearch
{
    /** Days around a date in which another movement of the same commitment counts as a duplicate. */
    public const int DUPLICATE_WINDOW_DAYS = 20;

    /** Without the issuer, only the same amount within these days counts: a few euros spent twice is common. */
    public const int SIMILAR_WINDOW_DAYS = 3;

    /** Days around the day of a payment receipt in which the expense it pays can have been recorded. */
    public const int PAYMENT_WINDOW_DAYS = 30;

    /** How many movements the person is offered to pick by hand. */
    public const int NEAR_LIMIT = 20;

    /**
     * The commitment linked to a card (the employment, for an income); with several, the one whose dates contain the
     * date. A one-off planned expense counts only within the duplicate window of its own date: it is not the
     * commitment of the later bills.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  Model|null  $card  the card of an expense
     * @param  Employment|null  $employment  the employment of an income
     * @param  CarbonImmutable|null  $date  the date of the paper
     */
    public function planned(string $kind, ?Model $card, ?Employment $employment, ?CarbonImmutable $date): PlannedExpense|PlannedIncome|null
    {
        $day = $date?->startOfDay();

        if ($kind === 'income') {
            if (! $employment instanceof Employment) {
                return null;
            }

            $rows = PlannedIncome::query()->where('employment_id', $employment->id)
                ->latest('start_date')->orderByDesc('id')->get();

            return $this->containing($rows, $day);
        }

        if (! $card instanceof Model) {
            return null;
        }

        $rows = PlannedExpense::query()
            ->where('card_type', $card->getMorphClass())->where('card_id', $card->getKey())
            ->latest('start_date')->orderByDesc('id')->get();

        $rows = $rows->filter(fn (PlannedExpense $planned): bool => $planned->is_recurring
            || ($day !== null && $planned->start_date !== null && IssuerOrder::daysApart($planned->start_date, $day) <= self::DUPLICATE_WINDOW_DAYS));

        return $this->containing($rows, $day);
    }

    /**
     * Another movement of the same commitment close to the date.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  PlannedExpense|PlannedIncome  $planned  the commitment
     * @param  CarbonImmutable  $date  the date of the paper
     */
    public function duplicate(string $kind, PlannedExpense|PlannedIncome $planned, CarbonImmutable $date): Expense|Income|null
    {
        $link = $kind === 'income' ? 'planned_income_id' : 'planned_expense_id';

        return $this->movements($kind)->where($link, $planned->id)
            ->whereBetween('date', $this->window($date, self::DUPLICATE_WINDOW_DAYS))
            ->orderBy('id')->first();
    }

    /**
     * A movement with the same amount close to the date, for the papers without a commitment: of the same issuer
     * within the duplicate window, of any issuer within a few days. For an expense the amount is the one without fee.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  float  $net  the amount of the paper
     * @param  CarbonImmutable  $date  the date of the paper
     * @param  int|null  $organizationId  the issuer, if known
     */
    public function similar(string $kind, float $net, CarbonImmutable $date, ?int $organizationId): Expense|Income|null
    {
        $days = $organizationId !== null ? self::DUPLICATE_WINDOW_DAYS : self::SIMILAR_WINDOW_DAYS;
        $query = $this->movements($kind)->whereBetween('date', $this->window($date, $days));
        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        /** @var Collection<int, Expense|Income> $rows */
        $rows = $query->orderBy('id')->get();

        return $rows->first(fn (Expense|Income $row): bool => $this->net($row) === Money::cents($net));
    }

    /**
     * The expense a payment receipt pays: same amount without the fee, the date within a month of the payment. An
     * expense of another issuer is not the one; one with no issuer stays. The same issuer comes first, then the
     * closest date, then the lowest id. `others` counts the ones left out, for the page to say so.
     *
     * @param  float  $net  the sum paid
     * @param  CarbonImmutable  $date  the day of the payment
     * @param  int|null  $organizationId  the creditor, if known
     * @return array{match: ?Expense, others: int}
     */
    public function paid(float $net, CarbonImmutable $date, ?int $organizationId): array
    {
        $day = $date->startOfDay();

        $found = Expense::query()->whereBetween('date', $this->window($date, self::PAYMENT_WINDOW_DAYS))->get()
            ->filter(fn (Expense $row): bool => $this->net($row) === Money::cents($net))
            ->filter(fn (Expense $row): bool => IssuerOrder::allows($row->organization_id, $organizationId))
            ->sort(fn (Expense $a, Expense $b): int => IssuerOrder::key($a->organization_id, $organizationId, $a->date ?? $day, $day, $a->id)
                <=> IssuerOrder::key($b->organization_id, $organizationId, $b->date ?? $day, $day, $b->id))
            ->values();

        return ['match' => $found->first(), 'others' => max($found->count() - 1, 0)];
    }

    /**
     * The one-off planned expense already there for a paper to pay (the same invoice imported twice): same amount,
     * the start within the duplicate window, of the same issuer; without the issuer, of any issuer within a few days.
     * The closest date wins, then the lowest id.
     *
     * @param  float  $amount  the amount of the paper
     * @param  CarbonImmutable  $date  the date of the paper
     * @param  int|null  $organizationId  the issuer, if known
     */
    public function plannedSimilar(float $amount, CarbonImmutable $date, ?int $organizationId): ?PlannedExpense
    {
        $days = $organizationId !== null ? self::DUPLICATE_WINDOW_DAYS : self::SIMILAR_WINDOW_DAYS;
        $day = $date->startOfDay();
        $query = PlannedExpense::query()->where('is_recurring', false)
            ->whereBetween('start_date', $this->window($date, $days));
        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query->orderBy('id')->get()
            ->filter(fn (PlannedExpense $row): bool => Money::cents($row->amount) === Money::cents($amount))
            ->sortBy(fn (PlannedExpense $row): int => IssuerOrder::daysApart($row->start_date ?? $day, $day), SORT_NUMERIC)
            ->first();
    }

    /**
     * The movements within the payment window of a date, the closest first (then the lowest id), at most
     * `NEAR_LIMIT`: those the person can pick by hand instead of the one found.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  CarbonImmutable  $date  the date of the paper
     * @return list<array{id: int, title: string, date: string, amount: float}>
     */
    public function near(string $kind, CarbonImmutable $date): array
    {
        $day = $date->startOfDay();

        /** @var Collection<int, Expense|Income> $rows */
        $rows = $this->movements($kind)->whereBetween('date', $this->window($date, self::PAYMENT_WINDOW_DAYS))->orderBy('id')->get();

        return array_values($rows
            ->sortBy(fn (Expense|Income $row): int => IssuerOrder::daysApart($row->date ?? $day, $day), SORT_NUMERIC)
            ->take(self::NEAR_LIMIT)
            ->map(fn (Expense|Income $row): array => [
                'id' => $row->id,
                'title' => (string) $row->title,
                'date' => ($row->date ?? $day)->toDateString(),
                'amount' => Money::cents($row->amount) / 100.0,
            ])
            ->all());
    }

    /**
     * The query of the movements of a kind.
     *
     * @param  string  $kind  `expense` or `income`
     * @return Builder<Expense>|Builder<Income>
     */
    private function movements(string $kind): Builder
    {
        /** @var Builder<Expense>|Builder<Income> $query the `@method` lines of the models widen `query()` to a model */
        $query = $kind === 'income' ? Income::query() : Expense::query();

        return $query;
    }

    /**
     * The amount of a movement without the fee (only an expense has one), in cents.
     *
     * @param  Expense|Income  $row  the movement
     */
    private function net(Expense|Income $row): int
    {
        return Money::cents($row->amount) - ($row instanceof Expense ? Money::cents($row->fee_amount) : 0);
    }

    /**
     * The first and last day, as dates, of a window around a date.
     *
     * @param  CarbonImmutable  $date  the centre
     * @param  int  $days  the days on each side
     * @return array{string, string}
     */
    private function window(CarbonImmutable $date, int $days): array
    {
        return [$date->subDays($days)->toDateString(), $date->addDays($days)->toDateString()];
    }

    /**
     * Of the commitments (newest start first), the one whose dates contain the day (ends included, an open end or
     * start counts as containing), otherwise the first.
     *
     * @template T of PlannedExpense|PlannedIncome
     *
     * @param  Collection<int, T>  $rows  the commitments
     * @param  CarbonImmutable|null  $day  the day of the paper
     * @return T|null
     */
    private function containing(Collection $rows, ?CarbonImmutable $day): PlannedExpense|PlannedIncome|null
    {
        if ($rows->count() <= 1 || ! $day instanceof CarbonImmutable) {
            return $rows->first();
        }

        return $rows->first(fn (PlannedExpense|PlannedIncome $planned): bool => ($planned->start_date === null || $planned->start_date->startOfDay()->lte($day))
            && ($planned->end_date === null || $planned->end_date->startOfDay()->gte($day)))
            ?? $rows->first();
    }
}
