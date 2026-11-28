<?php

declare(strict_types=1);

use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SyncBankConnection;
use App\Actions\Budget\SavePlanned;
use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankConnection;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\BankFilters;
use App\Services\Banks\FakeBankFeed;
use App\Services\Deadlines;
use App\Services\Reminders\NoticeList;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
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

/**
 * Two monthly Findomestic loans of the Bianchi, on the 10th from January 2026, each paid by a filter on the same words
 * (as two old rules on the same lender become two filters).
 *
 * @return array{PlannedExpense, PlannedExpense, BankFilter, BankFilter}
 */
function bankMatchingTwoLoans(string $first = '150', string $second = '200'): array
{
    $car = bankMatchingLuce(['title' => 'Prestito auto', 'amount' => $first, 'start_date' => '2026-01-10']);
    $kitchen = bankMatchingLuce(['title' => 'Prestito cucina', 'amount' => $second, 'start_date' => '2026-01-10']);

    return [$car, $kitchen, bankMatchingPays($car, ['FINDOMESTIC']), bankMatchingPays($kitchen, ['FINDOMESTIC'])];
}

test('two filters that pay take one movement: the planned item closest in amount is paid', function (): void {
    $transaction = insideBianchi(function (): BankTransaction {
        [$car, $kitchen, , $kitchenFilter] = bankMatchingTwoLoans();
        $transaction = bankMatchingTransaction('ADDEBITO SDD FINDOMESTIC BANCA', '-199.00', '2026-11-10');

        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(1)
            ->and($transaction->refresh()->expense?->planned_expense_id)->toBe($kitchen->id)
            ->and($transaction->bank_filter_id)->toBe($kitchenFilter->id)
            ->and(Expense::query()->where('planned_expense_id', $car->id)->exists())->toBeFalse();

        return $transaction;
    });

    expect($transaction->expense_id)->not->toBeNull();
});

test('saving one of two filters that pay settles what is closest to its planned item', function (): void {
    insideBianchi(function (): void {
        $near = bankMatchingTransaction('ADDEBITO SDD FINDOMESTIC BANCA', '-150.00', '2026-11-10');
        $far = bankMatchingTransaction('ADDEBITO SDD FINDOMESTIC BANCA', '-200.00', '2026-11-10');
        [$car, , $carFilter] = bankMatchingTwoLoans();

        expect(resolve(MatchBankTransactions::class)->forFilter($carFilter))->toBe(1)
            ->and($near->refresh()->expense?->planned_expense_id)->toBe($car->id)
            ->and($far->refresh()->expense_id)->toBeNull();
    });
});

test('two filters that pay at the same distance leave the movement to sort and name them', function (): void {
    $transaction = insideBianchi(function (): BankTransaction {
        bankMatchingTwoLoans('150', '200');
        $transaction = bankMatchingTransaction('ADDEBITO SDD FINDOMESTIC BANCA', '-175.00', '2026-11-10');

        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(0)
            ->and($transaction->refresh()->expense_id)->toBeNull()
            ->and(Expense::query()->count())->toBe(0);

        return $transaction;
    });

    $this->actingAs($this->marco)->get('/budget/bank')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.id', $transaction->id)
        ->where('rows.0.two_filters', ['Prestito auto', 'Prestito cucina']));
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

/**
 * The dual-fuel Enel bill of the Bianchi on 27/11/2026, with *Conto BPER* linked to a connection of the fake bank that
 * gives the debit of 21/11/2026, and Laura as a second account.
 *
 * @param  string  $amount  the amount of the debit
 * @return array{bill: array{luce: PlannedExpense, gas: PlannedExpense, account: FinancialAccount, document: Document, filters: array{luce: BankFilter, gas: BankFilter}}, connection: BankConnection}
 */
function bankMatchingDualFuel(FakeBankFeed $feed, string $amount = '-165.65'): array
{
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
    App::setLocale('it');
    Http::preventStrayRequests();
    User::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'role' => Role::Member]);
    [$connection] = bankMatchingConnection();
    $bill = enelDualFuelBill();
    $bill['account']->forceFill(['bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1'])->save();
    FinancialAccount::query()->where('name', 'Conto BancoPosta')->update(['bank_connection_id' => null, 'bank_account_uid' => null]);
    $feed->gives([new BankEntry('enel', CarbonImmutable::parse('2026-11-21'), null, (float) $amount, 'EUR', 'ADDEBITO SDD ENEL ENERGIA SPA', null, true)]);

    return ['bill' => $bill, 'connection' => $connection->refresh()];
}

test('the read of the bank pays Luce and Gas of the dual-fuel bill with one debit, and tells everyone once', function (): void {
    insideBianchi(function (): void {
        ['bill' => $bill, 'connection' => $connection] = bankMatchingDualFuel($this->feed);

        resolve(SyncBankConnection::class)->handle($connection);

        $transaction = BankTransaction::query()->where('provider_id', 'enel')->sole();
        $payments = Expense::query()->orderBy('planned_expense_id')->get();
        expect($payments->map(static fn (Expense $expense): string => $expense->title.' '.$expense->amount.' '.$expense->date?->toDateString())->all())
            ->toBe(['Luce novembre 2026 96.25 2026-11-21', 'Gas novembre 2026 69.40 2026-11-21'])
            ->and($payments->pluck('financial_account_id')->unique()->all())->toBe([$bill['account']->id])
            ->and($payments->pluck('bank_transaction_id')->unique()->all())->toBe([$transaction->id])
            ->and($payments->map(static fn (Expense $expense): array => $expense->documents()->pluck('documents.id')->all())->all())
            ->toBe([[$bill['document']->id], [$bill['document']->id]])
            ->and($transaction->expense_id)->toBe($payments[0]->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Rule)
            ->and($transaction->bank_filter_id)->toBe($bill['filters']['luce']->id)
            ->and(resolve(Deadlines::class)->find($bill['luce'], CarbonImmutable::parse('2026-11-23'))->isPaid())->toBeTrue()
            ->and(resolve(Deadlines::class)->find($bill['gas'], CarbonImmutable::parse('2026-11-30'))->isPaid())->toBeTrue();

        $users = User::query()->inFamily($transaction->family_id)->pluck('id');
        expect(ReminderNotice::query()->where('bank_transaction_id', $transaction->id)->pluck('user_id')->sort()->values()->all())->toBe($users->sort()->values()->all());

        foreach ($users as $user) {
            $row = resolve(NoticeList::class)->latest($user, CarbonImmutable::now('Europe/Rome'))['unread'][0];
            expect($row->title)->toBe('Pagata dalla banca · Luce e Gas novembre 2026')
                ->and($row->due_text)->toBe("165,65\u{A0}€ dal Conto BPER");
        }
    });
});

test('the debit of the dual-fuel bill links to the two payments already recorded, without a notice', function (): void {
    insideBianchi(function (): void {
        ['bill' => $bill, 'connection' => $connection] = bankMatchingDualFuel($this->feed);
        $paid = array_map(static function (array $row) use ($bill): Expense {
            $expense = Expense::factory()->create([
                'family_id' => $bill['account']->family_id, 'planned_expense_id' => $row[0]->id, 'occurrence_date' => $row[1],
                'date' => '2026-11-20', 'amount' => $row[2], 'financial_account_id' => $bill['account']->id,
            ]);
            $expense->documents()->attach($bill['document']);

            return $expense;
        }, [[$bill['luce'], '2026-11-23', 96.25], [$bill['gas'], '2026-11-30', 69.40]]);

        resolve(SyncBankConnection::class)->handle($connection);

        $transaction = BankTransaction::query()->where('provider_id', 'enel')->sole();
        expect($transaction->expense_id)->toBe($paid[0]->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and(Expense::query()->count())->toBe(2)
            ->and(Expense::query()->orderBy('id')->pluck('bank_transaction_id')->all())->toBe([$transaction->id, $transaction->id])
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
});

test('a debit that is not the sum of the bill pays the planned expense of the filter closest in amount', function (): void {
    insideBianchi(function (): void {
        ['bill' => $bill, 'connection' => $connection] = bankMatchingDualFuel($this->feed, '-170.00');

        resolve(SyncBankConnection::class)->handle($connection);

        $payment = Expense::query()->sole();
        expect($payment->planned_expense_id)->toBe($bill['luce']->id)
            ->and((float) $payment->amount)->toBe(170.0)
            ->and($payment->bank_transaction_id)->toBeNull()
            ->and(BankTransaction::query()->sole()->expense_id)->toBe($payment->id);
    });
});

test('saving the filter of Gas pays the dual-fuel bill with one debit', function (): void {
    insideBianchi(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
        $bill = enelDualFuelBill();
        $transaction = enelDualFuelDebit($bill['account']);

        expect(resolve(MatchBankTransactions::class)->forFilter($bill['filters']['gas']))->toBe(1)
            ->and(Expense::query()->orderBy('planned_expense_id')->pluck('amount')->all())->toBe(['96.25', '69.40'])
            ->and($transaction->refresh()->expense?->planned_expense_id)->toBe($bill['luce']->id);
    });
});

test('saving a filter of another planned expense leaves the dual-fuel debit alone', function (): void {
    insideBianchi(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
        $bill = enelDualFuelBill();
        $transaction = enelDualFuelDebit($bill['account']);
        $acqua = bankMatchingPays(bankMatchingLuce(['title' => 'Acqua']), ['ACQUEDOTTO']);

        expect(resolve(MatchBankTransactions::class)->forFilter($acqua))->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and($transaction->refresh()->expense_id)->toBeNull();
    });
});

test('a filter that does something else on the dual-fuel debit leaves it to the person, with the names', function (): void {
    insideBianchi(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
        $bill = enelDualFuelBill();
        $transaction = enelDualFuelDebit($bill['account']);
        BankFilter::factory()->create(['family_id' => $transaction->family_id, 'name' => 'Enel a parte', 'contains' => ['ENEL'], 'action' => BankFilterAction::Ignore]);

        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(0)
            ->and(Expense::query()->count())->toBe(0)
            ->and($transaction->refresh()->expense_id)->toBeNull()
            ->and($transaction->ignored_at)->toBeNull()
            ->and(resolve(BankFilters::class)->namesWhenShared($transaction))->toBe(['Luce', 'Gas', 'Enel a parte']);
    });
});

test('the commission of the dual-fuel debit joins the payment of Luce, the one the debit is linked to', function (): void {
    insideBianchi(function (): void {
        Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
        $bill = enelDualFuelBill();
        $mandate = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) ENEL ENERGIA S.P.A. CID.IT12ZZZ0000000000000000001 MAN.ENEL0042';
        $debit = enelDualFuelDebit($bill['account']);
        $debit->forceFill(['text' => $mandate])->save();
        $fee = enelDualFuelDebit($bill['account'], '-0.40');
        $fee->forceFill(['text' => 'COMMISSIONI '.$mandate, 'booked_on' => '2026-11-22', 'value_on' => '2026-11-22'])->save();

        expect(resolve(MatchBankTransactions::class)->handle([$debit->refresh()]))->toBe(1);

        $payments = Expense::query()->orderBy('planned_expense_id')->get();
        expect($payments->map(static fn (Expense $expense): array => [(float) $expense->amount, $expense->fee_amount === null ? null : (float) $expense->fee_amount])->all())
            ->toBe([[96.65, 0.4], [69.4, null]])
            ->and($fee->refresh()->expense_id)->toBe($payments[0]->id)
            ->and($fee->matched_by)->toBe(BankMatchSource::Fee)
            ->and($debit->refresh()->expense_id)->toBe($payments[0]->id);
    });
});
