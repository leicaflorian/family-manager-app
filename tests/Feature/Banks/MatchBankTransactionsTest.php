<?php

declare(strict_types=1);

use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SyncBankConnection;
use App\Actions\Budget\SavePlanned;
use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Models\BankConnection;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $this->feed);
});

/**
 * A connection of the Bianchi with a readable session and the BancoPosta account linked to it.
 *
 * @return array{BankConnection, FinancialAccount}
 */
function bankMatchingConnection(): array
{
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
    $connection = BankConnection::factory()->create(['family_id' => $familyId]);
    $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
    $account = FinancialAccount::factory()->create([
        'family_id' => $familyId, 'name' => 'Conto BancoPosta', 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1',
    ]);

    return [$connection->refresh(), $account];
}

/**
 * The monthly Luce of the Bianchi, on the 5th from January 2026.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankMatchingLuce(array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => 'Luce',
        'amount' => 72,
        'fee_amount' => null,
        'start_date' => '2026-01-05',
        'financial_account_id' => null,
        ...$attributes,
    ]);
}

/**
 * A filter of the Bianchi that pays the due date of the planned expense for the words given.
 *
 * @param  list<string>  $words  what the text must have
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankMatchingPays(PlannedExpense $planned, array $words = ['SDD', 'EDISON'], array $attributes = []): BankFilter
{
    return BankFilter::factory()->pays($planned)->create([
        'family_id' => $planned->family_id, 'name' => $planned->title, 'contains' => $words, ...$attributes,
    ]);
}

/**
 * A booked transaction of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankMatchingTransaction(string $text, string $amount, string $day, array $attributes = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'text' => $text,
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$attributes,
    ]);
}

test('a new transaction taken by one filter is settled', function (): void {
    insideBianchi(function (): void {
        [$connection, $account] = bankMatchingConnection();
        $luce = bankMatchingLuce();
        $filter = bankMatchingPays($luce);
        $this->feed->gives([
            new BankEntry('a', CarbonImmutable::parse('2026-11-05'), null, -72.40, 'EUR', 'ADDEBITO SDD EDISON ENERGIA', null, true),
            new BankEntry('b', CarbonImmutable::parse('2026-11-04'), null, -5.00, 'EUR', 'BAR CENTRALE', null, true),
        ]);

        expect(resolve(SyncBankConnection::class)->handle($connection))->toBe(2);

        $expense = Expense::query()->where('planned_expense_id', $luce->id)->sole();
        $paid = BankTransaction::query()->where('provider_id', 'a')->sole();
        expect($expense->occurrence_date?->toDateString())->toBe('2026-11-05')
            ->and($expense->financial_account_id)->toBe($account->id)
            ->and($paid->expense_id)->toBe($expense->id)
            ->and($paid->bank_filter_id)->toBe($filter->id)
            ->and(BankTransaction::query()->where('provider_id', 'b')->sole()->expense_id)->toBeNull();

        // The same read again finds nothing new and pays nothing twice
        resolve(SyncBankConnection::class)->handle($connection->refresh());
        expect(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1);
    });
});

test('one taken by two filters stays to sort and names them', function (): void {
    $transaction = insideBianchi(function (): BankTransaction {
        bankMatchingPays(bankMatchingLuce(), ['EDISON'], ['name' => 'Luce di casa']);
        BankFilter::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'name' => 'Edison ufficio', 'contains' => ['EDISON', 'ENERGIA']]);
        $transaction = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and($transaction->refresh()->expense_id)->toBeNull()
            ->and($transaction->bank_filter_id)->toBeNull();

        return $transaction;
    });

    $this->actingAs($this->marco)->get('/budget/bank')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.id', $transaction->id)
        ->where('rows.0.two_filters', ['Luce di casa', 'Edison ufficio']));
});

test('a transaction taken by one filter names no filters on its row', function (): void {
    insideBianchi(fn (): BankTransaction => bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05'));

    $this->actingAs($this->marco)->get('/budget/bank')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.two_filters', null));
});

test('saving a filter settles what is to sort and leaves what is linked', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatchingLuce();
        $july = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 7', '-70.10', '2026-07-06');
        $november = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 11', '-72.40', '2026-11-05');
        $september = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 9', '-72.10', '2026-09-05');
        $october = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 10', '-68.90', '2026-10-06');
        $other = Expense::factory()->create(['family_id' => $luce->family_id, 'title' => 'Bolletta vecchia', 'amount' => 60, 'date' => '2026-08-01']);
        $linked = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 8', '-60.00', '2026-08-03', ['expense_id' => $other->id, 'matched_by' => BankMatchSource::Created]);
        bankMatchingTransaction('BAR CENTRALE', '-5.00', '2026-10-06');

        $filter = bankMatchingPays($luce);

        expect(resolve(MatchBankTransactions::class)->forFilter($filter))->toBe(3);

        $payments = Expense::query()->where('planned_expense_id', $luce->id)->orderBy('id')->get();
        expect($payments->map(static fn (Expense $expense): string => $expense->occurrence_date?->toDateString().' '.$expense->amount)->all())
            ->toBe(['2026-09-05 72.10', '2026-10-05 68.90', '2026-11-05 72.40'])
            ->and($september->refresh()->expense_id)->toBe($payments[0]->id)
            ->and($october->refresh()->expense_id)->toBe($payments[1]->id)
            ->and($november->refresh()->expense_id)->toBe($payments[2]->id)
            ->and($july->refresh()->expense_id)->toBeNull()
            ->and($linked->refresh()->expense_id)->toBe($other->id)
            ->and($linked->matched_by)->toBe(BankMatchSource::Created)
            ->and($linked->bank_filter_id)->toBeNull();
    });
});

test('saving a filter leaves a transaction that another filter takes too', function (): void {
    insideBianchi(function (): void {
        $transaction = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        $filter = bankMatchingPays(bankMatchingLuce());
        BankFilter::factory()->create(['family_id' => $filter->family_id, 'contains' => ['EDISON'], 'action' => BankFilterAction::Ignore]);

        expect(resolve(MatchBankTransactions::class)->forFilter($filter))->toBe(0)
            ->and($transaction->refresh()->ignored_at)->toBeNull()
            ->and($transaction->expense_id)->toBeNull();
    });
});

test('a planned item with an old rule and no filter pays nothing, also when it is saved', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatchingLuce(['bank_match' => 'SDD.*EDISON']);
        $transaction = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        resolve(SavePlanned::class)->handle('expense', ['bank_match' => 'EDISON'], $luce);

        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and($luce->refresh()->bank_match)->toBe('EDISON');
    });
});

test('a pending one pays nothing until it is booked', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankMatchingConnection();
        $luce = bankMatchingLuce();
        bankMatchingPays($luce);
        $this->feed->gives([new BankEntry(null, null, CarbonImmutable::parse('2026-11-05'), -72.40, 'EUR', 'ADDEBITO SDD EDISON ENERGIA', null, false)]);

        resolve(SyncBankConnection::class)->handle($connection);
        expect(Expense::query()->count())->toBe(0);

        $this->feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-05'), null, -72.40, 'EUR', 'ADDEBITO SDD EDISON ENERGIA', null, true)]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());

        expect(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1);
    });
});

test('a declined transaction is never matched again', function (): void {
    insideBianchi(function (): void {
        $filter = bankMatchingPays(bankMatchingLuce(), ['EDISON']);
        $declined = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05', ['declined_at' => '2026-11-05 08:00:00']);

        expect(resolve(MatchBankTransactions::class)->handle([$declined]))->toBe(0)
            ->and(resolve(MatchBankTransactions::class)->forFilter($filter))->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and($declined->refresh()->expense_id)->toBeNull();
    });
});

test('only the transactions still to sort are matched, and the count says how many were settled', function (): void {
    insideBianchi(function (): void {
        bankMatchingPays(bankMatchingLuce(), ['EDISON']);
        $paid = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        $other = bankMatchingTransaction('BAR CENTRALE', '-5.00', '2026-11-05');
        $ignored = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-10-05', ['ignored_at' => '2026-10-06 08:00:00']);

        expect(resolve(MatchBankTransactions::class)->handle([$paid, $other, $ignored]))->toBe(1)
            ->and($paid->refresh()->matched_by)->toBe(BankMatchSource::Rule)
            ->and($ignored->refresh()->expense_id)->toBeNull();
    });
});

test('a foreign currency transaction pays nothing', function (): void {
    insideBianchi(function (): void {
        $filter = bankMatchingPays(bankMatchingLuce(), ['EDISON']);
        $dollars = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05', ['currency' => 'USD']);

        expect(resolve(MatchBankTransactions::class)->handle([$dollars]))->toBe(0)
            ->and(resolve(MatchBankTransactions::class)->forFilter($filter))->toBe(0)
            ->and(Expense::query()->count())->toBe(0);
    });
});

test('a failure while matching is reported and never leaves the read of the bank nor the save of a filter', function (): void {
    Exceptions::fake();

    insideBianchi(function (): void {
        [$connection] = bankMatchingConnection();
        $filter = bankMatchingPays(bankMatchingLuce());
        bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        // The filters are read from the database: a database error there
        BankFilter::retrieved(static fn () => throw new RuntimeException('The database went away.'));
        $this->feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-05'), null, -72.40, 'EUR', 'ADDEBITO SDD EDISON ENERGIA', null, true)]);

        expect(resolve(SyncBankConnection::class)->handle($connection))->toBe(1)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and(resolve(MatchBankTransactions::class)->forFilter($filter))->toBe(0)
            ->and(Expense::query()->count())->toBe(0);
    });

    Exceptions::assertReported(RuntimeException::class);
});
