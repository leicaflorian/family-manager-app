<?php

declare(strict_types=1);

use App\Services\Reading\DueSearch;
use Carbon\CarbonImmutable;

/**
 * Whether the reading offers "to pay" by itself.
 *
 * @param  string|null  $kind  the kind of the paper
 * @param  bool  $suggested  whether the expense is suggested
 * @param  string|null  $due  the due date
 * @param  string  $today  today
 */
function dueSuggestedFor(?string $kind, bool $suggested, ?string $due, string $today): bool
{
    return DueSearch::suggested(
        $kind,
        $suggested,
        $due === null ? null : CarbonImmutable::parse($due),
        CarbonImmutable::parse($today),
    );
}

describe('dueSuggested', function (): void {
    test('an invoice due after today is suggested as to pay', function (): void {
        expect(dueSuggestedFor('invoice', true, '2026-11-30', '2026-10-15'))->toBeTrue();
    });

    test('a due date already passed is not', function (): void {
        expect(dueSuggestedFor('invoice', true, '2026-11-30', '2026-12-02'))->toBeFalse();
    });

    test('a due date today is not', function (): void {
        expect(dueSuggestedFor('invoice', true, '2026-11-30', '2026-11-30'))->toBeFalse();
    });

    test('receipts and payment receipts are already paid', function (): void {
        expect(dueSuggestedFor('receipt', true, '2026-11-30', '2026-10-15'))->toBeFalse()
            ->and(dueSuggestedFor('payment_receipt', true, '2026-11-30', '2026-10-15'))->toBeFalse();
    });

    test('no due date, or not suggested, is not', function (): void {
        expect(dueSuggestedFor('invoice', true, null, '2026-10-15'))->toBeFalse()
            ->and(dueSuggestedFor('invoice', false, '2026-11-30', '2026-10-15'))->toBeFalse();
    });

    test('the due date is after today in Rome', function (): void {
        // 00:30 of the 16th in Rome is still the 15th in UTC: a bill due on the 16th is due today, not tomorrow
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 22:30:00', 'UTC'));

        try {
            $today = DueSearch::today();

            expect($today->toDateString())->toBe('2026-10-16')
                ->and(DueSearch::suggested('invoice', true, CarbonImmutable::parse('2026-10-16'), $today))->toBeFalse()
                ->and(DueSearch::suggested('invoice', true, CarbonImmutable::parse('2026-10-17'), $today))->toBeTrue();
        } finally {
            CarbonImmutable::setTestNow();
        }
    });
});
