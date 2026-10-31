<?php

declare(strict_types=1);

use App\Data\MovementData;
use App\Data\PaymentYearData;
use App\Support\PaymentPage;

/**
 * A payment of the given day, for the years of the tests.
 */
function pagePayment(int $id, string $date): MovementData
{
    return new MovementData(
        kind: 'expense', id: $id, title: "Payment {$id}", date: $date, amount: 10.0, fee_amount: null,
        account_id: null, account: null, category_id: null, category: null, icon: null, color: null,
        organization_id: null, organization: null, person_id: null, card: null, planned_id: null,
        occurrence_date: null, type: null, employment_id: null, documents: [], can_edit: true, can_delete: true,
    );
}

/**
 * Four payments in 2026 and three in 2025, the latest first.
 *
 * @return list<PaymentYearData>
 */
function pageYears(): array
{
    return [
        new PaymentYearData(2026, 40.0, 4, [pagePayment(7, '2026-10-01'), pagePayment(6, '2026-08-01'), pagePayment(5, '2026-05-01'), pagePayment(4, '2026-01-01')]),
        new PaymentYearData(2025, 30.0, 3, [pagePayment(3, '2025-12-01'), pagePayment(2, '2025-06-01'), pagePayment(1, '2025-02-01')]),
    ];
}

/**
 * The ids of the payments of each year of a page.
 *
 * @param  list<PaymentYearData>  $years
 * @return array<int, list<int>>
 */
function pageIds(array $years): array
{
    return array_combine(
        array_map(static fn (PaymentYearData $year): int => $year->year, $years),
        array_map(static fn (PaymentYearData $year): array => array_map(static fn (MovementData $payment): int => $payment->id, $year->payments), $years),
    );
}

test('a page takes the latest payments across the years, each year keeping its whole total', function (): void {
    $page = PaymentPage::of(pageYears(), 1, 5);

    expect(pageIds($page['years']))->toBe([2026 => [7, 6, 5, 4], 2025 => [3]])
        ->and($page['years'][1]->total)->toBe(30.0)
        ->and($page['years'][1]->count)->toBe(3)
        ->and($page['page'])->toBe(1)
        ->and($page['next'])->toBe(2);
});

test('the last page has no next one', function (): void {
    $page = PaymentPage::of(pageYears(), 2, 5);

    expect(pageIds($page['years']))->toBe([2025 => [2, 1]])
        ->and($page['page'])->toBe(2)
        ->and($page['next'])->toBeNull();
});

test('a page past the last one is the first', function (): void {
    $page = PaymentPage::of(pageYears(), 9, 5);

    expect(pageIds($page['years']))->toBe([2026 => [7, 6, 5, 4], 2025 => [3]])
        ->and($page['page'])->toBe(1);
});

test('no payments make an empty first page', function (): void {
    expect(PaymentPage::of([], 1, 5))->toBe(['years' => [], 'page' => 1, 'next' => null]);
});
