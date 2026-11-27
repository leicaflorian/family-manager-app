<?php

declare(strict_types=1);

use App\Actions\Banks\LinkRecordedMovement;
use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    registerFamily();
});

/**
 * A debit of the Bianchi still to sort.
 */
function linkRecordedMovementDebit(): BankTransaction
{
    return BankTransaction::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'amount' => '-20.00', 'booked_on' => '2026-11-03']);
}

test('links the transaction to the recorded expense without creating one', function (): void {
    insideBianchi(function (): void {
        $transaction = linkRecordedMovementDebit();
        $expense = Expense::factory()->create(['family_id' => $transaction->family_id, 'amount' => 20, 'date' => '2026-10-30']);

        $linked = resolve(LinkRecordedMovement::class)->handle($transaction, $expense);

        expect($linked->is($expense))->toBeTrue()
            ->and($transaction->refresh()->expense_id)->toBe($expense->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and(Expense::query()->count())->toBe(1);
    });
});

test('refuses an expense another transaction already has', function (): void {
    insideBianchi(function (): void {
        $transaction = linkRecordedMovementDebit();
        $expense = Expense::factory()->create(['family_id' => $transaction->family_id, 'amount' => 20]);
        BankTransaction::factory()->create(['family_id' => $transaction->family_id, 'expense_id' => $expense->id]);

        expect(fn () => resolve(LinkRecordedMovement::class)->handle($transaction, $expense))->toThrow(ValidationException::class)
            ->and($transaction->refresh()->expense_id)->toBeNull();
    });
});

test('refuses an income for a debit', function (): void {
    insideBianchi(function (): void {
        $transaction = linkRecordedMovementDebit();
        $income = Income::factory()->create(['family_id' => $transaction->family_id, 'amount' => 20]);

        expect(fn () => resolve(LinkRecordedMovement::class)->handle($transaction, $income))->toThrow(ValidationException::class)
            ->and($transaction->refresh()->income_id)->toBeNull();
    });
});
