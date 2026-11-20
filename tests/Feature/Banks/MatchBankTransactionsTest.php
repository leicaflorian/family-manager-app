<?php

declare(strict_types=1);

use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SyncBankConnection;
use App\Actions\Budget\SavePlanned;
use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\BankMatchSource;
use App\Models\BankConnection;
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

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    registerFamily();
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
        'bank_match' => 'SDD.*EDISON',
        'amount' => 72,
        'fee_amount' => null,
        'start_date' => '2026-01-05',
        'financial_account_id' => null,
        ...$attributes,
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

test('new booked transactions are matched during the sync', function (): void {
    insideBianchi(function (): void {
        [$connection, $account] = bankMatchingConnection();
        $luce = bankMatchingLuce();
        $this->feed->gives([
            new BankEntry('a', CarbonImmutable::parse('2026-11-05'), null, -72.40, 'EUR', 'ADDEBITO SDD EDISON ENERGIA', null, true),
            new BankEntry('b', CarbonImmutable::parse('2026-11-04'), null, -5.00, 'EUR', 'BAR CENTRALE', null, true),
        ]);

        expect(resolve(SyncBankConnection::class)->handle($connection))->toBe(2);

        $expense = Expense::query()->where('planned_expense_id', $luce->id)->sole();
        expect($expense->occurrence_date?->toDateString())->toBe('2026-11-05')
            ->and($expense->financial_account_id)->toBe($account->id)
            ->and(BankTransaction::query()->where('provider_id', 'a')->sole()->expense_id)->toBe($expense->id)
            ->and(BankTransaction::query()->where('provider_id', 'b')->sole()->expense_id)->toBeNull();

        // The same read again finds nothing new and pays nothing twice
        resolve(SyncBankConnection::class)->handle($connection->refresh());
        expect(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1);
    });
});

test('a pending one pays nothing until it is booked', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankMatchingConnection();
        $luce = bankMatchingLuce();
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
        $luce = bankMatchingLuce(['bank_match' => 'EDISON']);
        $declined = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05', ['declined_at' => '2026-11-05 08:00:00']);

        expect(resolve(MatchBankTransactions::class)->handle([$declined]))->toBe(0)
            ->and(resolve(MatchBankTransactions::class)->forPlanned($luce))->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and($declined->refresh()->expense_id)->toBeNull();
    });
});

test('only the transactions still to sort are matched, and the count says how many paid', function (): void {
    insideBianchi(function (): void {
        bankMatchingLuce(['bank_match' => 'EDISON']);
        $paid = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        $other = bankMatchingTransaction('BAR CENTRALE', '-5.00', '2026-11-05');
        $ignored = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-10-05', ['ignored_at' => '2026-10-06 08:00:00']);

        expect(resolve(MatchBankTransactions::class)->handle([$paid, $other, $ignored]))->toBe(1)
            ->and($paid->refresh()->matched_by)->toBe(BankMatchSource::Rule)
            ->and($ignored->refresh()->expense_id)->toBeNull();
    });
});

test('saving a rule pays the open due dates of the last 90 days, oldest first', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatchingLuce(['bank_match' => null]);
        $july = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 7', '-70.10', '2026-07-06');
        $november = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 11', '-72.40', '2026-11-05');
        $september = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 9', '-72.10', '2026-09-05');
        $october = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 10', '-68.90', '2026-10-06');
        bankMatchingTransaction('BAR CENTRALE', '-5.00', '2026-10-06');

        resolve(SavePlanned::class)->handle('expense', ['bank_match' => 'SDD.*EDISON'], $luce);

        $payments = Expense::query()->where('planned_expense_id', $luce->id)->orderBy('id')->get();
        expect($payments->map(static fn (Expense $expense): string => $expense->occurrence_date?->toDateString().' '.$expense->amount)->all())
            ->toBe(['2026-09-05 72.10', '2026-10-05 68.90', '2026-11-05 72.40'])
            ->and($september->refresh()->expense_id)->toBe($payments[0]->id)
            ->and($october->refresh()->expense_id)->toBe($payments[1]->id)
            ->and($november->refresh()->expense_id)->toBe($payments[2]->id)
            ->and($july->refresh()->expense_id)->toBeNull();

        // Saving it again without changing the rule pays nothing more
        $later = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA FATT. 8', '-71.00', '2026-08-10');
        resolve(SavePlanned::class)->handle('expense', ['bank_match' => 'SDD.*EDISON', 'title' => 'Luce di casa'], $luce->refresh());
        expect($later->refresh()->expense_id)->toBeNull();
    });
});

test('a new planned expense with a rule pays its open due dates', function (): void {
    insideBianchi(function (): void {
        $transaction = bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        $luce = resolve(SavePlanned::class)->handle('expense', [
            'title' => 'Luce', 'amount' => 72, 'start_date' => '2026-01-05', 'is_recurring' => true, 'interval' => 1,
            'interval_unit' => 'month', 'is_estimated' => false, 'bank_match' => 'EDISON', 'financial_account_id' => $transaction->financial_account_id,
        ]);

        expect($transaction->refresh()->expense_id)->not->toBeNull()
            ->and($transaction->expense?->planned_expense_id)->toBe($luce->id);
    });
});

test('a foreign currency transaction pays nothing', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatchingLuce(['bank_match' => 'EDISON']);
        $dollars = bankMatchingTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05', ['currency' => 'USD']);

        expect(resolve(MatchBankTransactions::class)->handle([$dollars]))->toBe(0)
            ->and(resolve(MatchBankTransactions::class)->forPlanned($luce))->toBe(0)
            ->and(Expense::query()->count())->toBe(0);
    });
});

test('a failure while matching is reported and never leaves the read of the bank nor the save of a rule', function (): void {
    Exceptions::fake();

    insideBianchi(function (): void {
        [$connection] = bankMatchingConnection();
        $luce = bankMatchingLuce(['bank_match' => null]);
        bankMatchingTransaction('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        // The matcher reads the planned items: a database error there
        PlannedExpense::retrieved(static fn () => throw new RuntimeException('The database went away.'));
        $this->feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-05'), null, -72.40, 'EUR', 'ADDEBITO SDD EDISON ENERGIA', null, true)]);

        expect(resolve(SyncBankConnection::class)->handle($connection))->toBe(1)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::Active);

        $saved = resolve(SavePlanned::class)->handle('expense', ['bank_match' => 'SDD.*EDISON'], $luce);

        expect($saved->bank_match)->toBe('SDD.*EDISON')
            ->and(Expense::query()->count())->toBe(0);
    });

    Exceptions::assertReported(RuntimeException::class);
});
