<?php

declare(strict_types=1);

use App\Enums\BankTransactionStatus;
use App\Models\BankConnection;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Services\Banks\BankFilters;
use App\Support\Banks\BankFilterCriteria;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\DB;

/**
 * A family with one account linked to a bank, the way the filters see it.
 *
 * @return array{Family, FinancialAccount}
 */
function bankFiltersFamily(): array
{
    $family = Family::factory()->create();
    $account = FinancialAccount::factory()->for($family)->create([
        'bank_connection_id' => BankConnection::factory()->create(['family_id' => $family->id])->id,
    ]);
    CurrentFamily::set($family->id);

    return [$family, $account];
}

test('gives the filters that take a transaction', function (): void {
    [$family, $account] = bankFiltersFamily();
    $edison = BankFilter::factory()->for($family)->create(['name' => 'Edison', 'contains' => ['edison']]);
    BankFilter::factory()->for($family)->create(['name' => 'Gas', 'contains' => ['italgas']]);
    $transaction = BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'Addebito EDISON energia', 'amount' => '-50.00']);

    $found = (new BankFilters)->matching($transaction);

    expect($found->pluck('id')->all())->toBe([$edison->id]);
});

test('a filter of another direction or account does not match', function (): void {
    [$family, $account] = bankFiltersFamily();
    $other = FinancialAccount::factory()->for($family)->create([
        'bank_connection_id' => $account->bank_connection_id,
    ]);
    BankFilter::factory()->for($family)->create(['name' => 'Income', 'direction' => 'income', 'contains' => ['edison']]);
    BankFilter::factory()->for($family)->create(['name' => 'Other account', 'financial_account_id' => $other->id, 'contains' => ['edison']]);
    $transaction = BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'EDISON', 'amount' => '-50.00']);

    expect((new BankFilters)->matching($transaction))->toBeEmpty();
});

test('an unsafe filter never matches', function (): void {
    [$family, $account] = bankFiltersFamily();
    BankFilter::factory()->for($family)->create(['name' => 'Short', 'contains' => ['ab']]);
    BankFilter::factory()->for($family)->create(['name' => 'Empty', 'contains' => []]);
    DB::table('bank_filters')->insert([
        'family_id' => $family->id, 'name' => 'Everything', 'contains' => '[]', 'excludes' => '[]', 'direction' => 'expense',
        'pattern' => '.*', 'fee_mode' => 'none', 'action' => 'ignore', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $transaction = BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'ab anything', 'amount' => '-5.00']);

    expect((new BankFilters)->matching($transaction))->toBeEmpty();
});

test('reads the filters once for many transactions', function (): void {
    [$family, $account] = bankFiltersFamily();
    BankFilter::factory()->for($family)->create(['contains' => ['edison']]);
    $transactions = BankTransaction::factory()->for($family)->count(5)->create(['financial_account_id' => $account->id, 'text' => 'EDISON', 'amount' => '-5.00']);
    $filters = new BankFilters;
    $filters->matching($transactions->firstOrFail());

    DB::enableQueryLog();
    foreach ($transactions as $transaction) {
        $filters->matching($transaction);
    }
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'bank_filters'))->count();
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('taken lists what the criteria take in the last 90 days, linked ones too', function (): void {
    [$family, $account] = bankFiltersFamily();
    $make = fn (array $extra) => BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'amount' => '-5.00', ...$extra]);
    $new = $make(['text' => 'EDISON', 'booked_on' => now()->subDays(2)->toDateString()]);
    $linked = $make(['text' => 'EDISON', 'booked_on' => now()->subDays(10)->toDateString(), 'expense_id' => Expense::factory()->for($family)->create()->id]);
    $make(['text' => 'EDISON', 'booked_on' => now()->subDays(120)->toDateString()]);
    $make(['text' => 'ITALGAS', 'booked_on' => now()->subDays(3)->toDateString()]);

    $taken = (new BankFilters)->taken(new BankFilterCriteria(contains: ['edison']));

    expect($taken->pluck('id')->all())->toBe([$new->id, $linked->id]);
});

test('taken leaves out the excepted transaction', function (): void {
    [$family, $account] = bankFiltersFamily();
    $make = fn (int $days) => BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'amount' => '-5.00', 'text' => 'EDISON', 'booked_on' => now()->subDays($days)->toDateString()]);
    $first = $make(2);
    $second = $make(4);

    $taken = (new BankFilters)->taken(new BankFilterCriteria(contains: ['edison']), except: $first->id);

    expect($taken->pluck('id')->all())->toBe([$second->id]);
});

test("never reads another family's filters or transactions", function (): void {
    [$family, $account] = bankFiltersFamily();
    $stranger = Family::factory()->create();
    $strangerAccount = FinancialAccount::factory()->for($stranger)->create();
    BankFilter::factory()->for($stranger)->create(['contains' => ['edison']]);
    $foreign = BankTransaction::factory()->for($stranger)->create(['financial_account_id' => $strangerAccount->id, 'text' => 'EDISON', 'amount' => '-5.00']);
    $mine = BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'EDISON', 'amount' => '-5.00']);

    expect((new BankFilters)->matching($mine))->toBeEmpty()
        ->and((new BankFilters)->taken(new BankFilterCriteria(contains: ['edison']))->pluck('id')->all())->toBe([$mine->id])
        ->and($foreign->id)->not->toBe($mine->id);
});

test('a pending or non-euro transaction matches no filter', function (): void {
    [$family, $account] = bankFiltersFamily();
    BankFilter::factory()->for($family)->create(['contains' => ['edison']]);
    $make = fn (array $extra) => BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'EDISON', 'amount' => '-5.00', ...$extra]);
    $filters = new BankFilters;

    expect($filters->matching($make(['status' => BankTransactionStatus::Pending])))->toBeEmpty()
        ->and($filters->matching($make(['currency' => 'USD'])))->toBeEmpty()
        ->and($filters->matching($make([])))->toHaveCount(1);
});

test('taken leaves out non-euro rows', function (): void {
    [$family, $account] = bankFiltersFamily();
    $euro = BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'EDISON', 'amount' => '-5.00']);
    BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'text' => 'EDISON', 'amount' => '-5.00', 'currency' => 'USD']);

    expect((new BankFilters)->taken(new BankFilterCriteria(contains: ['edison']))->pluck('id')->all())->toBe([$euro->id]);
});

test('names the filters when two or more take a booked euro transaction that is not a commission', function (): void {
    [$family, $account] = bankFiltersFamily();
    BankFilter::factory()->for($family)->create(['name' => 'Edison casa', 'contains' => ['edison']]);
    BankFilter::factory()->for($family)->create(['name' => 'Edison ufficio', 'contains' => ['edison', 'energia']]);
    $row = fn (array $state): BankTransaction => BankTransaction::factory()->for($family)->create(['financial_account_id' => $account->id, 'amount' => '-50.00', ...$state]);
    $filters = new BankFilters;

    expect($filters->namesWhenShared($row(['text' => 'Addebito EDISON energia'])))->toBe(['Edison casa', 'Edison ufficio'])
        ->and($filters->namesWhenShared($row(['text' => 'EDISON luce'])))->toBeNull()
        ->and($filters->namesWhenShared($row(['text' => 'COMMISSIONI EDISON energia'])))->toBeNull()
        ->and($filters->namesWhenShared($row(['text' => 'EDISON energia', 'status' => BankTransactionStatus::Pending])))->toBeNull()
        ->and($filters->namesWhenShared($row(['text' => 'EDISON energia', 'currency' => 'USD'])))->toBeNull();
});
