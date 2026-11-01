<?php

declare(strict_types=1);

use App\Enums\IntervalUnit;
use App\Models\PlannedExpense;
use App\Support\Reminders\Renewals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * A day, as the rules read it.
 */
function renewalDay(string $day): CarbonImmutable
{
    return CarbonImmutable::parse($day, 'Europe/Rome');
}

/**
 * A fee, without the database.
 */
function renewalFee(int $id, ?int $interval, IntervalUnit $unit): PlannedExpense
{
    return (new PlannedExpense)->forceFill(['id' => $id, 'interval' => $interval, 'interval_unit' => $unit]);
}

/**
 * The text of a date, to compare.
 */
function renewalText(?CarbonImmutable $date): ?string
{
    return $date?->toDateString();
}

describe('renewals', function (): void {
    test('the renewal moves on by one period of the fee', function (): void {
        expect(renewalText(Renewals::next(renewalDay('2026-10-03'), 1, 'year')))->toBe('2027-10-03')
            ->and(renewalText(Renewals::next(renewalDay('2026-10-26'), 1, 'month')))->toBe('2026-11-26')
            ->and(renewalText(Renewals::next(renewalDay('2027-01-31'), 1, 'month')))->toBe('2027-02-28')
            ->and(renewalText(Renewals::next(renewalDay('2027-02-28'), 1, 'month')))->toBe('2027-03-28')
            ->and(renewalText(Renewals::next(renewalDay('2026-01-02'), 2, 'year')))->toBe('2028-01-02')
            ->and(renewalText(Renewals::next(renewalDay('2026-10-03'), 2, 'week')))->toBe('2026-10-17');
    });

    test('without a period the renewal stays where it is', function (): void {
        expect(Renewals::next(renewalDay('2026-10-03'), 0, 'year'))->toBeNull()
            ->and(Renewals::next(renewalDay('2026-10-03'), 1, ''))->toBeNull()
            ->and(Renewals::next(renewalDay('2026-10-03'), 1, 'day'))->toBeNull();
    });

    test('a payment confirms a renewal at most 30 days after it', function (): void {
        expect(Renewals::paymentRenews(renewalDay('2026-10-03'), renewalDay('2026-10-14'), 1, 'year'))->toBeTrue()
            ->and(Renewals::paymentRenews(renewalDay('2027-07-01'), renewalDay('2027-06-05'), 1, 'year'))->toBeTrue()
            ->and(Renewals::paymentRenews(renewalDay('2026-11-13'), renewalDay('2026-10-14'), 1, 'year'))->toBeTrue()
            ->and(Renewals::paymentRenews(renewalDay('2026-11-14'), renewalDay('2026-10-14'), 1, 'year'))->toBeFalse();
    });

    test('a renewal already confirmed, or an old invoice, is not moved again', function (): void {
        expect(Renewals::paymentRenews(renewalDay('2027-10-03'), renewalDay('2026-10-14'), 1, 'year'))->toBeFalse()
            ->and(Renewals::paymentRenews(renewalDay('2027-10-03'), renewalDay('2025-10-10'), 1, 'year'))->toBeFalse();
    });

    test('with a short fee the window is half a period, so the same payment never moves the renewal twice', function (): void {
        // Monthly, renewal 15/04 paid 15/04: moved to 15/05; the date of the payment corrected to 16/04 must not move it again.
        expect(Renewals::paymentRenews(renewalDay('2026-04-15'), renewalDay('2026-04-15'), 1, 'month'))->toBeTrue()
            ->and(Renewals::paymentRenews(renewalDay('2026-05-15'), renewalDay('2026-04-16'), 1, 'month'))->toBeFalse()
            // February: renewal 15/02 paid 15/02 moved to 15/03; the same payment recorded twice must not move it to 15/04.
            ->and(Renewals::paymentRenews(renewalDay('2027-03-15'), renewalDay('2027-02-15'), 1, 'month'))->toBeFalse()
            // Weekly: a payment saved again must not move it a week more.
            ->and(Renewals::paymentRenews(renewalDay('2026-10-10'), renewalDay('2026-10-03'), 1, 'week'))->toBeFalse()
            ->and(Renewals::paymentRenews(renewalDay('2026-10-05'), renewalDay('2026-10-03'), 1, 'week'))->toBeTrue();
    });

    test('RENEWAL_FIELD is the yearly renewal of a subscription', function (): void {
        expect(Renewals::FIELD)->toBe('subscriptions.renewal_date')
            ->and(Renewals::NOTICE_DAYS)->toBe(30);
    });
});

describe('the limits of the renewal confirmation', function (): void {
    test('the renewal follows the yearly fee, then the monthly, then the weekly; the oldest on a tie', function (): void {
        $yearly = renewalFee(5, 1, IntervalUnit::Year);
        $monthly = renewalFee(2, 1, IntervalUnit::Month);
        $weekly = renewalFee(1, 1, IntervalUnit::Week);

        expect(Renewals::fee(new Collection([$monthly, $yearly, $weekly])))->toBe($yearly)
            ->and(Renewals::fee(new Collection([$weekly, $monthly])))->toBe($monthly)
            ->and(Renewals::fee(new Collection([renewalFee(9, 1, IntervalUnit::Year), $yearly])))->toBe($yearly);
    });

    test('a fee without a period gives no renewal', function (): void {
        expect(Renewals::fee(new Collection))->toBeNull()
            ->and(Renewals::fee(new Collection([renewalFee(2, 0, IntervalUnit::Month)])))->toBeNull();
    });

    test('reopening a confirmed reminder brings the renewal back, only if it is still the one the confirmation set', function (): void {
        expect(renewalText(Renewals::reopened(renewalDay('2026-10-03'), renewalDay('2027-10-03'), 1, 'year')))->toBe('2026-10-03')
            ->and(Renewals::reopened(renewalDay('2026-10-03'), renewalDay('2027-11-15'), 1, 'year'))->toBeNull()
            ->and(Renewals::reopened(renewalDay('2026-10-03'), renewalDay('2026-10-03'), 1, 'year'))->toBeNull()
            ->and(Renewals::reopened(renewalDay('2026-10-03'), renewalDay('2027-10-03'), 0, ''))->toBeNull();
    });
});

describe('a renewal that cannot be confirmed', function (): void {
    beforeEach(fn () => App::setLocale('it'));

    afterEach(fn () => App::setLocale('en'));

    test('the message names the subscription and says what to check', function (): void {
        expect(Renewals::notRenewedMessage(['Fatture in Cloud']))->toBe(
            "Il rinnovo di Fatture in Cloud non è stato spostato: l'abbonamento non è più attivo o la sua data è cambiata. Controlla la scheda dell'abbonamento.",
        );
    });

    test('with several subscriptions they are listed together', function (): void {
        expect(Renewals::notRenewedMessage(['Netflix', 'Php Storm']))->toBe(
            "Il rinnovo di Netflix e Php Storm non è stato spostato: l'abbonamento non è più attivo o la sua data è cambiata. Controlla la scheda dell'abbonamento.",
        )->and(Renewals::notRenewedMessage(['A', 'B', 'C']))->toContain('A, B e C');
    });
});
