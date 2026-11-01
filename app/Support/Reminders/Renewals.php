<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Models\PlannedExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The rules of the renewal of a subscription, without the database: the renewal is confirmed by hand, never by
 * itself, because the charge may not have gone through. Two gestures confirm it, marking its reminder as done and
 * recording a payment of its fee; either moves the renewal date on by one period of the fee.
 *
 * It is the rewrite of `nextRenewal`, `renewalFee`, `reopenedRenewal`, `paymentRenews` and `notRenewedMessage` of the
 * Directus extension `reminders-automation`.
 */
final class Renewals
{
    /** The `source_field` of the reminder of the renewal of a subscription. */
    public const string FIELD = 'subscriptions.renewal_date';

    /**
     * A payment of the fee confirms the renewal when the renewal is at most this many days after the payment; with a
     * fee shorter than two months, half its period, so that the same payment saved again never moves it twice.
     */
    public const int NOTICE_DAYS = 30;

    /** The shortest length of a period, in days: February for the months. */
    private const array PERIOD_DAYS = ['week' => 7, 'month' => 28, 'year' => 365];

    /** The kinds of fee the renewal follows, the first that exists wins. */
    private const array FEE_ORDER = ['year', 'month', 'week'];

    /**
     * The renewal after a date, one period of the fee later. A month that is too short keeps its last day (31/01 plus
     * a month is 28/02, and from there it stays on the 28).
     *
     * @param  CarbonImmutable  $date  the current renewal
     * @param  int  $interval  how many units make a period
     * @param  string  $unit  `week`, `month` or `year`
     * @return CarbonImmutable|null the next renewal, null when the fee has no period to count
     */
    public static function next(CarbonImmutable $date, int $interval, string $unit): ?CarbonImmutable
    {
        if ($interval < 1) {
            return null;
        }

        return match ($unit) {
            'week' => $date->addDays(7 * $interval),
            'month' => $date->addMonthsNoOverflow($interval),
            'year' => $date->addMonthsNoOverflow(12 * $interval),
            default => null,
        };
    }

    /**
     * The fee the renewal follows, among the planned expenses of a subscription: the yearly one, then the monthly,
     * then the weekly; the oldest first when two are alike.
     *
     * @param  Collection<int, PlannedExpense>  $fees  the planned expenses of the subscription
     */
    public static function fee(Collection $fees): ?PlannedExpense
    {
        return $fees
            ->filter(static fn (PlannedExpense $fee): bool => $fee->interval >= 1)
            ->sortBy([
                static fn (PlannedExpense $a, PlannedExpense $b): int => array_search($a->interval_unit->value, self::FEE_ORDER, true) <=> array_search($b->interval_unit->value, self::FEE_ORDER, true),
                static fn (PlannedExpense $a, PlannedExpense $b): int => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * Whether a payment made on a day confirms the renewal: not an old invoice, not a renewal already moved.
     *
     * @param  CarbonImmutable  $renewal  the renewal date of the subscription
     * @param  CarbonImmutable  $paid  the day of the payment
     * @param  int  $interval  how many units make a period of the fee
     * @param  string  $unit  the unit of the fee
     */
    public static function paymentRenews(CarbonImmutable $renewal, CarbonImmutable $paid, int $interval, string $unit): bool
    {
        $period = (self::PERIOD_DAYS[$unit] ?? 0) * $interval;
        $window = $period > 0 ? min(self::NOTICE_DAYS, intdiv($period, 2)) : self::NOTICE_DAYS;

        return $renewal->toDateString() <= $paid->addDays($window)->toDateString();
    }

    /**
     * Where the renewal goes back to when a confirmed reminder is reopened: its date, but only if the renewal is still
     * the one the confirmation set (not changed by hand meanwhile).
     *
     * @param  CarbonImmutable  $due  the date of the reminder
     * @param  CarbonImmutable  $current  the renewal date of the subscription now
     * @param  int  $interval  how many units make a period of the fee
     * @param  string  $unit  the unit of the fee
     * @return CarbonImmutable|null the date to go back to, null when the renewal is to be left alone
     */
    public static function reopened(CarbonImmutable $due, CarbonImmutable $current, int $interval, string $unit): ?CarbonImmutable
    {
        $next = self::next($due, $interval, $unit);

        return $next instanceof CarbonImmutable && $next->toDateString() === $current->toDateString() ? $due : null;
    }

    /**
     * Why marking a renewal reminder as done is refused: its subscription could not be moved on.
     *
     * @param  list<string>  $names  the names of the subscriptions
     */
    public static function notRenewedMessage(array $names): string
    {
        $last = array_pop($names);
        $list = $names === [] ? (string) $last : implode(', ', $names).' '.__('and').' '.$last;

        return __('The renewal of :names was not moved: the subscription is no longer active or its date has changed. Check the subscription card.', ['names' => $list]);
    }
}
