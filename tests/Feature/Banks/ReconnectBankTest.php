<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Services\Banks\FakeBankFeed;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    Cache::forget('bank-institutions:IT');
    app()->instance(BankFeed::class, new FakeBankFeed);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/**
 * Connects the trial bank through the pages and reads it at once (the queue of the tests runs jobs on the spot).
 */
function reconnectConnect(): void
{
    test()->post(route('banks.store'), ['institution' => 'Banca di prova']);
    test()->get(route('banks.callback', ['code' => 'fake', 'state' => (string) session('bank_connect.state')]));
}

test('connecting the same bank twice keeps one account and its transactions once', function (): void {
    reconnectConnect();
    $count = BankTransaction::query()->count();
    expect($count)->toBeGreaterThan(0);

    reconnectConnect();

    expect(BankConnection::query()->count())->toBe(1)
        ->and(FinancialAccount::query()->count())->toBe(1)
        ->and(BankTransaction::query()->count())->toBe($count);
});

test('disconnecting a bank and connecting it again reuses the account and does not copy the transactions', function (): void {
    reconnectConnect();
    $account = FinancialAccount::query()->sole();
    // A transaction that paid an expense stays when the bank goes, and must not come back as a second one
    $paid = BankTransaction::query()->where('status', 'booked')->orderBy('id')->firstOrFail();
    $paid->forceFill(['expense_id' => Expense::factory()->create(['family_id' => $this->marco->family_id])->id])->save();
    $count = BankTransaction::query()->count();

    $this->delete(route('banks.destroy', BankConnection::query()->sole()));
    reconnectConnect();

    expect(FinancialAccount::query()->count())->toBe(1)
        ->and(FinancialAccount::query()->sole()->id)->toBe($account->id)
        ->and(BankTransaction::query()->where('provider_id', $paid->provider_id)->count())->toBe(1)
        ->and(BankTransaction::query()->count())->toBe($count);
});

test('an account tied to the bank without its key, as the trial seeder leaves it, is found again', function (): void {
    reconnectConnect();
    $account = FinancialAccount::query()->sole();
    $account->forceFill(['bank_account_key' => null])->save();
    $count = BankTransaction::query()->count();

    reconnectConnect();

    expect(FinancialAccount::query()->count())->toBe(1)
        ->and($account->refresh()->bank_account_key)->toBe('fake-account-key')
        ->and(BankTransaction::query()->count())->toBe($count);
});
