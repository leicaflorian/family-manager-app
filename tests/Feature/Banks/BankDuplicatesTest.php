<?php

declare(strict_types=1);

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
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

test('finds the payment recorded seven days before', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-03']);

        expect(resolve(BankDuplicates::class)->findAny($transaction, $planned)?->is($hand))->toBeTrue();
    });
});

test('does not find the one of eight days before', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-02']);

        expect(resolve(BankDuplicates::class)->findAny($transaction, $planned))->toBeNull();
    });
});

test('an income payment by hand is found for a planned income', function (): void {
    insideBianchi(function (): void {
        $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
        $planned = PlannedIncome::factory()->create(['family_id' => $familyId, 'amount' => 1500, 'start_date' => '2026-01-27']);
        $transaction = BankTransaction::factory()->create(['family_id' => $familyId, 'amount' => '1500.00', 'booked_on' => '2026-11-27']);
        $hand = Income::factory()->create(['family_id' => $familyId, 'planned_income_id' => $planned->id, 'amount' => 1500, 'date' => '2026-11-26']);

        expect(resolve(BankDuplicates::class)->findAny($transaction, $planned)?->is($hand))->toBeTrue();
    });
});

test('one already linked to another bank transaction is not', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-10']);
        BankTransaction::factory()->create(['family_id' => $planned->family_id, 'expense_id' => $hand->id]);

        expect(resolve(BankDuplicates::class)->findAny($transaction, $planned))->toBeNull();
    });
});

test('one linked to this same bank transaction is still found', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-10']);
        $transaction->update(['expense_id' => $hand->id]);

        expect(resolve(BankDuplicates::class)->findAny($transaction, $planned)?->is($hand))->toBeTrue();
    });
});

test('a different amount is not', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418.01, 'date' => '2026-11-10']);

        expect(resolve(BankDuplicates::class)->findAny($transaction, $planned))->toBeNull();
    });
});

test('an expense with a fee transaction is not taken', function (): void {
    insideBianchi(function (): void {
        [$planned, $transaction] = bankDuplicatesSetup();
        $hand = Expense::factory()->create(['family_id' => $planned->family_id, 'planned_expense_id' => $planned->id, 'amount' => 418, 'date' => '2026-11-10']);
        // Only the commission of another debit is in it: the expense is still free for a debit
        $fee = BankTransaction::factory()->create(['family_id' => $planned->family_id, 'expense_id' => $hand->id, 'matched_by' => BankMatchSource::Fee]);

        expect(resolve(BankDuplicates::class)->isFree($hand, $transaction))->toBeTrue()
            ->and(resolve(BankDuplicates::class)->findAny($transaction, $planned)?->is($hand))->toBeTrue()
            ->and($fee->refresh()->expense_id)->toBe($hand->id);
    });
});

test('finds the recorded expense of 10,40 for a debit of 10,00 with a commission of 0,40', function (): void {
    insideBianchi(function (): void {
        $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
        $account = FinancialAccount::factory()->create(['family_id' => $familyId]);
        $mandate = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
        $row = static fn (string $text, string $amount, string $day): BankTransaction => BankTransaction::factory()->create([
            'family_id' => $familyId, 'financial_account_id' => $account->id, 'text' => $text, 'amount' => $amount, 'booked_on' => $day, 'value_on' => $day,
        ]);
        $planned = PlannedExpense::factory()->create(['family_id' => $familyId, 'title' => 'Ricarica Satispay', 'amount' => 10, 'fee_amount' => 0.4, 'start_date' => '2026-01-24']);
        $debit = $row($mandate, '-10.00', '2026-11-24');
        $row('COMMISSIONI '.$mandate, '-0.40', '2026-11-25');
        $hand = Expense::factory()->create(['family_id' => $familyId, 'planned_expense_id' => $planned->id, 'amount' => 10.4, 'date' => '2026-11-24']);

        expect(resolve(BankDuplicates::class)->findAny($debit, $planned)?->is($hand))->toBeTrue();
    });
});
