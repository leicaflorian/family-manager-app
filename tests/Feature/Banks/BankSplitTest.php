<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Services\Banks\BankSplit;
use App\Support\Deadline;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 09:00', 'Europe/Rome'));
    registerFamily();
});

/**
 * The payment of a due date of the dual-fuel bill, recorded by hand with the bill.
 *
 * @param  array{luce: PlannedExpense, gas: PlannedExpense, account: FinancialAccount, document: Document}  $bill
 */
function bankSplitPaid(array $bill, PlannedExpense $planned, string $due, float $amount): Expense
{
    $expense = Expense::factory()->create([
        'family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'occurrence_date' => $due,
        'date' => '2026-11-21', 'amount' => $amount, 'financial_account_id' => $bill['account']->id,
    ]);
    $expense->documents()->attach($bill['document']);

    return $expense;
}

/**
 * The due dates of a split as «title date».
 *
 * @param  list<Deadline>  $deadlines
 * @return list<string>
 */
function bankSplitDates(array $deadlines): array
{
    return array_map(static fn (Deadline $deadline): string => $deadline->planned->title.' '.$deadline->occurrence->originalDate->toDateString(), $deadlines);
}

test('the debit of 165,65 € pays Luce and Gas the bill waits on, Luce first', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();

        $match = resolve(BankSplit::class)->find(enelDualFuelDebit($bill['account']));

        expect($match?->mode)->toBe('pay')
            ->and(bankSplitDates($match->deadlines))->toBe(['Luce 2026-11-23', 'Gas 2026-11-30'])
            ->and($match->payments)->toBe([])
            ->and($match->documentId)->toBe($bill['document']->id);
    });
});

test('the two payments already recorded with the bill are linked together', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();
        $luce = bankSplitPaid($bill, $bill['luce'], '2026-11-23', 96.25);
        $gas = bankSplitPaid($bill, $bill['gas'], '2026-11-30', 69.40);

        $match = resolve(BankSplit::class)->find(enelDualFuelDebit($bill['account']));

        expect($match?->mode)->toBe('link')
            ->and(array_map(static fn (Expense $expense): int => $expense->id, $match->payments))->toBe([$luce->id, $gas->id])
            ->and(bankSplitDates($match->deadlines))->toBe(['Luce 2026-11-23', 'Gas 2026-11-30'])
            ->and($match->documentId)->toBe($bill['document']->id);
    });
});

test('a debit of 170,00 € is no split', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();

        expect(resolve(BankSplit::class)->find(enelDualFuelDebit($bill['account'], '-170.00')))->toBeNull();
    });
});

test('due dates waiting on two different documents are no split', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();
        $bill['document']->waitingPlans()->detach($bill['gas']->id);
        $other = Document::factory()->create(['family_id' => $bill['gas']->family_id, 'date' => '2026-11-05', 'planned_expense_id' => $bill['gas']->id]);

        expect($other->id)->not->toBe($bill['document']->id)
            ->and(resolve(BankSplit::class)->find(enelDualFuelDebit($bill['account'])))->toBeNull();
    });
});

test('one planned expense recognising the debit is no split', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();
        $bill['gas']->forceFill(['bank_match' => 'ENI PLENITUDE'])->save();

        expect(resolve(BankSplit::class)->find(enelDualFuelDebit($bill['account'])))->toBeNull();
    });
});

test('an income is no split', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();

        expect(resolve(BankSplit::class)->find(enelDualFuelDebit($bill['account'], '165.65')))->toBeNull();
    });
});
