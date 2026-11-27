<?php

declare(strict_types=1);

use App\Enums\BankTransactionStatus;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Services\Banks\BankFees;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->account = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
});

/**
 * A booked Satispay direct debit or commission of the family of Marco.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankFeesRow(string $day, string $amount, bool $fee = false, array $state = []): BankTransaction
{
    $mandate = 'CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';

    return BankTransaction::factory()->create([
        'family_id' => test()->marco->family_id,
        'financial_account_id' => test()->account->id,
        'text' => ($fee ? 'COMMISSIONI ' : '').'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. '.$mandate,
        'booked_on' => $day,
        'value_on' => $day,
        'amount' => $amount,
        ...$state,
    ]);
}

it('pairs the commission of 25/11 with the debit of 24/11', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00');
    $fee = bankFeesRow('2026-11-25', '-0.40', true);
    $service = resolve(BankFees::class);

    expect($service->feeOf($debit)?->is($fee))->toBeTrue()
        ->and($service->debitOf($fee)?->is($debit))->toBeTrue()
        ->and($service->feeOf($fee))->toBeNull()
        ->and($service->debitOf($debit))->toBeNull()
        ->and($service->pairs(collect([$debit, $fee]))[$debit->id]->is($fee))->toBeTrue();
});

it('not across accounts', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00');
    $other = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
    $fee = bankFeesRow('2026-11-25', '-0.40', true, ['financial_account_id' => $other->id]);

    expect(resolve(BankFees::class)->feeOf($debit))->toBeNull()
        ->and(resolve(BankFees::class)->debitOf($fee))->toBeNull();
});

it('not across families', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00');
    $foreign = Family::factory()->create();
    BankTransaction::factory()->create([
        'family_id' => $foreign->id,
        'financial_account_id' => FinancialAccount::factory()->create(['family_id' => $foreign->id])->id,
        'text' => 'COMMISSIONI DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900',
        'booked_on' => '2026-11-25',
        'amount' => '-0.40',
    ]);

    expect(resolve(BankFees::class)->feeOf($debit))->toBeNull();
});

it('not six days apart', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00');
    bankFeesRow('2026-11-30', '-0.40', true);
    $near = bankFeesRow('2026-11-29', '-0.40', true);

    expect(resolve(BankFees::class)->feeOf($debit)?->is($near))->toBeTrue();
    $near->delete();
    expect(resolve(BankFees::class)->feeOf($debit))->toBeNull();
});

it('not when the commission is bigger than the debit', function (): void {
    $debit = bankFeesRow('2026-11-24', '-0.30');
    bankFeesRow('2026-11-25', '-0.40', true);

    expect(resolve(BankFees::class)->feeOf($debit))->toBeNull();
});

it('not in the other direction', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00');
    bankFeesRow('2026-11-25', '0.40', true);

    expect(resolve(BankFees::class)->feeOf($debit))->toBeNull();
});

it('two debits of the same mandate: each takes the closest commission, none twice', function (): void {
    $first = bankFeesRow('2026-11-20', '-10.00');
    $second = bankFeesRow('2026-11-24', '-10.00');
    $near = bankFeesRow('2026-11-25', '-0.40', true);
    $far = bankFeesRow('2026-11-22', '-0.40', true);
    $service = resolve(BankFees::class);

    // The 22nd is closer to the 20th (2 days) than the 25th (5): each debit gets its own
    expect($service->feeOf($first)?->is($far))->toBeTrue()
        ->and($service->feeOf($second)?->is($near))->toBeTrue()
        ->and($service->debitOf($far)?->is($first))->toBeTrue()
        ->and($service->debitOf($near)?->is($second))->toBeTrue();

    $pairs = $service->pairs(collect([$first, $second, $near, $far]));
    expect($pairs)->toHaveCount(2)
        ->and($pairs[$first->id]->is($far))->toBeTrue()
        ->and($pairs[$second->id]->is($near))->toBeTrue();
});

it('one commission for two debits goes to the closest one', function (): void {
    $first = bankFeesRow('2026-11-20', '-10.00');
    $second = bankFeesRow('2026-11-24', '-10.00');
    $fee = bankFeesRow('2026-11-25', '-0.40', true);
    $service = resolve(BankFees::class);

    expect($service->feeOf($first))->toBeNull()
        ->and($service->feeOf($second)?->is($fee))->toBeTrue()
        ->and($service->debitOf($fee)?->is($second))->toBeTrue();
});

it('a commission whose debit is linked is an orphan', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00', state: ['expense_id' => Expense::factory()->create(['family_id' => $this->marco->family_id])->id]);
    $fee = bankFeesRow('2026-11-25', '-0.40', true);
    $service = resolve(BankFees::class);

    expect($service->debitOf($fee))->toBeNull()
        ->and($service->debitOf($fee, includeSorted: true)?->is($debit))->toBeTrue()
        ->and($service->pairs(collect([$fee])))->toBe([]);
});

it('pairs finds a commission that is not in the collection with one query', function (): void {
    $debits = [bankFeesRow('2026-11-24', '-10.00'), bankFeesRow('2026-10-24', '-10.00')];
    $fees = [bankFeesRow('2026-11-25', '-0.40', true), bankFeesRow('2026-10-25', '-0.40', true)];
    $rows = collect($debits);

    DB::enableQueryLog();
    $pairs = resolve(BankFees::class)->pairs($rows);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(1)
        ->and($pairs[$debits[0]->id]->is($fees[0]))->toBeTrue()
        ->and($pairs[$debits[1]->id]->is($fees[1]))->toBeTrue();
});

it('a pending transaction is never paired', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00');
    bankFeesRow('2026-11-25', '-0.40', true, ['status' => BankTransactionStatus::Pending]);

    expect(resolve(BankFees::class)->feeOf($debit))->toBeNull();
});

it('counts whole calendar days across the March clock change', function (): void {
    $debit = bankFeesRow('2026-03-26', '-10.00');
    $five = bankFeesRow('2026-03-31', '-0.40', true);
    $six = bankFeesRow('2026-04-01', '-0.40', true);
    $service = resolve(BankFees::class);

    expect($service->feeOf($debit)?->is($five))->toBeTrue()
        ->and($service->debitOf($six))->toBeNull();

    $five->delete();
    expect($service->feeOf($debit))->toBeNull()
        ->and($service->pairs(collect([$debit, $six])))->toBe([]);
});

it('reads a commission exactly five days later in late October, also from outside the collection', function (): void {
    $debit = bankFeesRow('2026-10-21', '-10.00');
    $fee = bankFeesRow('2026-10-26', '-0.40', true);
    $service = resolve(BankFees::class);

    expect($service->feeOf($debit)?->is($fee))->toBeTrue()
        ->and($service->debitOf($fee)?->is($debit))->toBeTrue()
        ->and($service->pairs(collect([$debit]))[$debit->id]->is($fee))->toBeTrue();
});

it('a pending debit gets no commission from pairs', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00', state: ['status' => BankTransactionStatus::Pending]);
    $fee = bankFeesRow('2026-11-25', '-0.40', true);

    expect(resolve(BankFees::class)->pairs(collect([$debit, $fee])))->toBe([]);
});

it('a debit in another currency gets no commission from pairs', function (): void {
    $debit = bankFeesRow('2026-11-24', '-10.00', state: ['currency' => 'USD']);
    $fee = bankFeesRow('2026-11-25', '-0.40', true);

    expect(resolve(BankFees::class)->pairs(collect([$debit, $fee])))->toBe([]);
});
