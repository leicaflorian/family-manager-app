<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CardBudgetData;
use App\Data\CardNumberData;
use App\Data\PaymentYearData;
use App\Models\Employment;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\Utility;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The figures of the payments of a card: what was paid in a year, the average payment, what the due dates of the
 * next 12 months add up to, and the words of the number of payments. Pure functions of the payments already read.
 */
final class CardPaymentFigures
{
    /**
     * The figures of *Payments of a card*: what was paid this year and the average payment with what is planned.
     *
     * @param  Model  $card  the card
     * @param  CardBudgetData  $budget  its payments, from `CardBudgets::of()`
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<CardNumberData>
     */
    public static function of(Model $card, CardBudgetData $budget, CarbonImmutable $today): array
    {
        $year = array_find($budget->payments, static fn (PaymentYearData $data): bool => $data->year === $today->year);
        $planned = count($budget->planned) === 1 ? $budget->planned[0]->amount : null;
        $bill = $card instanceof Utility;
        $hint = match (true) {
            $planned === null => $bill ? __('per bill') : __('per payment'),
            $budget->kind === 'income' => __('per payment, :amount expected'),
            default => $bill ? __('per bill, :amount planned') : __('per payment, :amount planned'),
        };

        return [
            ...self::paidIn($card, $budget->payments, $today->year, self::paidLabel($card, $today->year)),
            ...($year === null || $year->count === 0 ? [] : [new CardNumberData(__('On average'), round($year->total / $year->count, 2), hint: $hint, hint_amount: $planned)]),
        ];
    }

    /**
     * The words of the number of payments of each year of a card.
     *
     * @param  Model  $card  the card
     * @param  CardBudgetData  $budget  its payments, from `CardBudgets::of()`
     * @return array<int, string> by year, for example «9 bills»
     */
    public static function counts(Model $card, CardBudgetData $budget): array
    {
        $counts = [];

        foreach ($budget->payments as $data) {
            $counts[$data->year] = self::countText($card, $data->count);
        }

        return $counts;
    }

    /**
     * The words of the number of payments of a card in a year, as the payments of a utility count bills.
     *
     * @param  Model  $card  the card
     * @param  int  $count  how many payments
     * @return string for example «9 bills»
     */
    public static function countText(Model $card, int $count): string
    {
        return match (true) {
            $card instanceof Utility => trans_choice('{1} :count bill|[2,*] :count bills', $count),
            $card instanceof InsurancePolicy => trans_choice('{1} :count premium|[2,*] :count premiums', $count),
            $card instanceof Loan => trans_choice('{1} :count instalment|[2,*] :count instalments', $count),
            $card instanceof Employment => trans_choice('{1} :count salary received|[2,*] :count salaries received', $count),
            default => trans_choice('{1} :count payment|[2,*] :count payments', $count),
        };
    }

    /**
     * The label of what was paid (or received) in a year.
     *
     * @param  Model  $card  the card
     * @param  int  $year  the year
     */
    public static function paidLabel(Model $card, int $year): string
    {
        return $card instanceof Employment ? __('In :year', ['year' => $year]) : __('Paid in :year', ['year' => $year]);
    }

    /**
     * The figure of what was paid in a year, with the number of payments; none when nothing was paid.
     *
     * @param  Model  $card  the card, for the words of the number
     * @param  array<PaymentYearData>  $payments  the payments by year
     * @param  int  $year  the year
     * @param  string  $label  the label of the figure
     * @return list<CardNumberData>
     */
    public static function paidIn(Model $card, array $payments, int $year, string $label): array
    {
        $paid = array_find($payments, static fn (PaymentYearData $data): bool => $data->year === $year);

        return [new CardNumberData($label, $paid->total ?? 0.0, hint: self::countText($card, $paid->count ?? 0))];
    }

    /**
     * *What you pay*: the due dates of the next 12 months, exceptions applied.
     *
     * @param  list<Deadline>  $found  the due dates
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function whatYouPay(array $found, CarbonImmutable $today): CardNumberData
    {
        return self::inYear($found, $today, __('What you pay'), static fn (int $count): string => __('in the next 12 months'));
    }

    /**
     * A figure of the due dates of the next 12 months, from today to the same day a year later less one day.
     *
     * @param  list<Deadline>  $found  the due dates
     * @param  CarbonImmutable  $today  today in Rome
     * @param  string  $label  the label of the figure
     * @param  callable(int): string  $hint  the line below, from the number of due dates
     */
    public static function inYear(array $found, CarbonImmutable $today, string $label, callable $hint): CardNumberData
    {
        $first = $today->toDateString();
        $last = $today->addYear()->subDay()->toDateString();
        $inYear = array_filter($found, static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() >= $first && $deadline->occurrence->date->toDateString() <= $last);

        return new CardNumberData($label, self::sum($inYear), hint: $hint(count($inYear)));
    }

    /**
     * The sum of the amounts of some due dates.
     *
     * @param  array<Deadline>  $deadlines  the due dates
     */
    public static function sum(array $deadlines): float
    {
        return round(array_sum(array_map(static fn (Deadline $deadline): float => $deadline->occurrence->amount, $deadlines)), 2);
    }
}
