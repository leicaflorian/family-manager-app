<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Services\Banks\RecurringBankTexts;
use App\Support\Banks\BankMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-20 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->account = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]);
});

/**
 * A booked transaction of the family of Marco.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankRecurringBankTextsRow(string $day, string $amount = '-13.99', array $state = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => test()->marco->family_id,
        'financial_account_id' => test()->account->id,
        'text' => 'ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV',
        'booked_on' => $day,
        'value_on' => $day,
        'amount' => $amount,
        ...$state,
    ]);
}

it('the same text three months in a row is recurring', function (): void {
    bankRecurringBankTextsRow('2026-09-12');
    bankRecurringBankTextsRow('2026-10-12');
    bankRecurringBankTextsRow('2026-11-12');
    // Another family's text never counts
    BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id, 'text' => 'UN ALTRO ABBONAMENTO', 'booked_on' => '2026-11-02']);

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([BankMatch::signature('ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV')]);
});

it('numbers and the day of the month do not change the text', function (): void {
    bankRecurringBankTextsRow('2026-09-12', state: ['text' => 'ADDEBITO SDD EDISON FATT. 0001']);
    bankRecurringBankTextsRow('2026-10-12', state: ['text' => 'ADDEBITO SDD EDISON FATT. 0002']);
    bankRecurringBankTextsRow('2026-11-12', state: ['text' => 'ADDEBITO SDD EDISON FATT. 0003']);

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([BankMatch::signature('ADDEBITO SDD EDISON FATT.')]);
});

it('two months are not', function (): void {
    bankRecurringBankTextsRow('2026-10-12');
    bankRecurringBankTextsRow('2026-11-12');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);
});

it('a gap month breaks it', function (): void {
    bankRecurringBankTextsRow('2026-08-12');
    bankRecurringBankTextsRow('2026-10-12');
    bankRecurringBankTextsRow('2026-11-12');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);
});

it('amounts more than 20% apart are not', function (): void {
    bankRecurringBankTextsRow('2026-09-12', '-10.00');
    bankRecurringBankTextsRow('2026-10-12', '-12.00');
    bankRecurringBankTextsRow('2026-11-12', '-12.01');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);

    // Exactly 20% from the first still counts
    BankTransaction::query()->where('amount', '-12.01')->update(['amount' => '-12.00']);
    expect(resolve(RecurringBankTexts::class)->signatures())->toHaveCount(1);
});

it('the direction and the account must be the same', function (): void {
    bankRecurringBankTextsRow('2026-09-12');
    bankRecurringBankTextsRow('2026-10-12', '13.99');
    bankRecurringBankTextsRow('2026-11-12');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);

    BankTransaction::query()->where('amount', '13.99')->update(['amount' => '-13.99']);
    BankTransaction::query()->where('booked_on', '2026-10-12')->update(['financial_account_id' => FinancialAccount::factory()->create(['family_id' => $this->marco->family_id])->id]);

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);
});

it('a transaction already paid by a planned expense is not counted', function (): void {
    bankRecurringBankTextsRow('2026-09-12');
    bankRecurringBankTextsRow('2026-10-12', state: ['expense_id' => Expense::factory()->create(['family_id' => $this->marco->family_id])->id]);
    bankRecurringBankTextsRow('2026-11-12');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);
});

it('pending, ignored, other currency and older than four months do not count', function (): void {
    bankRecurringBankTextsRow('2026-09-12', state: ['currency' => 'USD']);
    bankRecurringBankTextsRow('2026-10-12', state: ['ignored_at' => now()]);
    bankRecurringBankTextsRow('2026-11-12');
    bankRecurringBankTextsRow('2026-05-12');
    bankRecurringBankTextsRow('2026-06-12');
    bankRecurringBankTextsRow('2026-07-12');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);
});

it('a chain that the saved planned expense could not pay is not offered', function (): void {
    // The oldest is more than the days of a first read away
    bankRecurringBankTextsRow('2026-08-12');
    bankRecurringBankTextsRow('2026-09-12');
    bankRecurringBankTextsRow('2026-10-12');

    expect(resolve(RecurringBankTexts::class)->signatures())->toBe([]);
});

it('reads the family with one query', function (): void {
    bankRecurringBankTextsRow('2026-11-12');
    bankRecurringBankTextsRow('2026-10-12');
    bankRecurringBankTextsRow('2026-09-12');

    DB::enableQueryLog();
    resolve(RecurringBankTexts::class)->signatures();

    expect(DB::getQueryLog())->toHaveCount(1);
});
