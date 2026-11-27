<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankDuplicates;

beforeEach(function (): void {
    registerFamily();
});

/**
 * A planned expense of the Bianchi and a bank transaction of 418,00 € on a day.
 *
 * @return array{PlannedExpense, BankTransaction}
 */
function bankDuplicatesSetup(): array
{
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
    $planned = PlannedExpense::factory()->create(['family_id' => $familyId, 'title' => 'Assicurazione', 'amount' => 418, 'start_date' => '2026-11-01']);
    $transaction = BankTransaction::factory()->create(['family_id' => $familyId, 'amount' => '-418.00', 'booked_on' => '2026-11-10', 'value_on' => '2026-11-10']);

    return [$planned, $transaction];
}

test('a payment recorded by hand with the same amount within five days is found', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-05']);

        expect(resolve(BankDuplicates::class)->find($transaction, $planned)?->is($hand))->toBeTrue();

        $hand->update(['date' => '2026-11-04']);
        expect(resolve(BankDuplicates::class)->find($transaction, $planned))->toBeNull();
    });
});

test('an income payment by hand is found for a planned income', function (): void {
    insideBianchi(function (): void {
        $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
        $planned = PlannedIncome::factory()->create(['family_id' => $familyId, 'amount' => 1500, 'start_date' => '2026-01-27']);
        $transaction = BankTransaction::factory()->create(['family_id' => $familyId, 'amount' => '1500.00', 'booked_on' => '2026-11-27']);
        $hand = Income::factory()->create(['family_id' => $familyId, 'planned_income_id' => $planned->id, 'amount' => 1500, 'date' => '2026-11-26']);

        expect(resolve(BankDuplicates::class)->find($transaction, $planned)?->is($hand))->toBeTrue();
    });
});

test('one already linked to another bank transaction is not', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-10']);
        BankTransaction::factory()->create(['family_id' => $planned->family_id, 'expense_id' => $hand->id]);

        expect(resolve(BankDuplicates::class)->find($transaction, $planned))->toBeNull();
    });
});

test('one linked to this same bank transaction is still found', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-10']);
        $transaction->update(['expense_id' => $hand->id]);

        expect(resolve(BankDuplicates::class)->find($transaction, $planned)?->is($hand))->toBeTrue();
    });
});

test('a different amount is not', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418.01, 'date' => '2026-11-10']);

        expect(resolve(BankDuplicates::class)->find($transaction, $planned))->toBeNull();
    });
});

test('the Gas payment of a dual-fuel debit belongs to that debit: not free nor found for another one', function (): void {
    insideBianchi(function (): void {
        $bill = enelDualFuelBill();
        $debit = enelDualFuelDebit($bill['account']);
        $luce = Expense::factory()->create(['family_id' => $debit->family_id, 'planned_expense_id' => $bill['luce']->id, 'amount' => 96.25, 'date' => '2026-11-21']);
        $gas = Expense::factory()->create(['family_id' => $debit->family_id, 'planned_expense_id' => $bill['gas']->id, 'amount' => 69.40, 'date' => '2026-11-21']);
        $debit->forceFill(['expense_id' => $luce->id])->save();
        $gas->forceFill(['bank_transaction_id' => $debit->id])->save();
        $other = enelDualFuelDebit($bill['account'], '-69.40');
        $duplicates = resolve(BankDuplicates::class);

        expect($duplicates->isFree($gas, $other))->toBeFalse()
            ->and($duplicates->isFree($gas, $debit))->toBeTrue()
            ->and($duplicates->find($other, $bill['gas']))->toBeNull();
    });
});
