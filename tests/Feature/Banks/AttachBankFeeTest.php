<?php

declare(strict_types=1);

use App\Actions\Banks\AcceptBankProposal;
use App\Actions\Banks\AttachBankFee;
use App\Actions\Banks\DetachBankFee;
use App\Actions\Banks\LinkRecordedMovement;
use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SyncBankConnection;
use App\Contracts\BankFeed;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Enums\BankMatchSource;
use App\Models\BankConnection;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-24 07:00', 'Europe/Rome'));
    registerFamily();
    $this->feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $this->feed);
});

/** The text of the Satispay direct debit of BancoPosta, with the commission word in front when asked. */
function attachBankFeeText(bool $fee = false): string
{
    return ($fee ? 'COMMISSIONI ' : '').'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
}

/** The BancoPosta account of the Bianchi, made once. */
function attachBankFeeAccount(): FinancialAccount
{
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;

    return FinancialAccount::query()->where('name', 'Conto BancoPosta')->first()
        ?? FinancialAccount::factory()->create(['family_id' => $familyId, 'name' => 'Conto BancoPosta']);
}

/**
 * A booked Satispay debit or commission of the Bianchi on BancoPosta.
 *
 * @param  array<string, mixed>  $state  more columns
 */
function attachBankFeeRow(string $day, string $amount, bool $fee = false, array $state = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'financial_account_id' => attachBankFeeAccount()->id,
        'text' => attachBankFeeText($fee),
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$state,
    ]);
}

/**
 * A debit of 10,00 € on 24/11 linked to its expense of 10,00 €, as a settling leaves them.
 *
 * @return array{BankTransaction, Expense}
 */
function attachBankFeeLinked(): array
{
    $debit = attachBankFeeRow('2026-11-24', '-10.00');
    $expense = Expense::factory()->create(['family_id' => $debit->family_id, 'title' => 'Ricarica Satispay', 'amount' => 10, 'fee_amount' => null, 'date' => '2026-11-24']);
    $debit->linkTo($expense, BankMatchSource::Rule);

    return [$debit, $expense];
}

test('the expense of 10,00 becomes 10,40 with a fee of 0,40', function (): void {
    insideBianchi(function (): void {
        [, $expense] = attachBankFeeLinked();
        $fee = attachBankFeeRow('2026-11-25', '-0.40', true);

        expect(resolve(AttachBankFee::class)->handle($fee, $expense))->toBeTrue();

        $expense->refresh();
        $fee->refresh();
        expect((float) $expense->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and($fee->expense_id)->toBe($expense->id)
            ->and($fee->matched_by)->toBe(BankMatchSource::Fee);
    });
});

test('a second run adds nothing', function (): void {
    insideBianchi(function (): void {
        [, $expense] = attachBankFeeLinked();
        $fee = attachBankFeeRow('2026-11-25', '-0.40', true);

        resolve(AttachBankFee::class)->handle($fee, $expense);

        expect(resolve(AttachBankFee::class)->handle($fee, $expense))->toBeFalse();
        resolve(AttachBankFee::class)->forNew([$fee->refresh()]);

        expect((float) $expense->refresh()->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4);
    });
});

test('the same commission read again is not added twice', function (): void {
    insideBianchi(function (): void {
        [, $expense] = attachBankFeeLinked();
        $fee = attachBankFeeRow('2026-11-25', '-0.40', true);
        resolve(AttachBankFee::class)->handle($fee, $expense);
        // The bank gives it again with another id
        $again = attachBankFeeRow('2026-11-25', '-0.40', true);

        expect(resolve(AttachBankFee::class)->handle($again, $expense))->toBeFalse();
        resolve(AttachBankFee::class)->forNew([$again]);

        expect((float) $expense->refresh()->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and($again->refresh()->expense_id)->toBeNull();
    });
});

test('a commission that arrives after its debit is attached at the next read', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00', 'Europe/Rome'));
        $connection = BankConnection::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]);
        $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
        $account = FinancialAccount::factory()->create(['family_id' => $connection->family_id, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1']);
        $satispay = PlannedExpense::factory()->create([
            'family_id' => $connection->family_id, 'title' => 'Ricarica Satispay', 'amount' => 10.0,
            'fee_amount' => null, 'is_estimated' => false, 'start_date' => '2026-01-24', 'financial_account_id' => $account->id,
        ]);
        BankFilter::factory()->pays($satispay)->create(['family_id' => $connection->family_id, 'contains' => ['MAN.SAT0023101207360112900'], 'fee_mode' => BankFilterFee::Auto]);
        $debit = new BankEntry('debit-1', CarbonImmutable::parse('2026-11-24'), CarbonImmutable::parse('2026-11-24'), -10.0, 'EUR', attachBankFeeText(), null, true);
        $fee = new BankEntry('fee-1', CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-25'), -0.40, 'EUR', attachBankFeeText(true), null, true);

        $this->feed->gives([$debit]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());
        $expense = Expense::query()->where('title', 'like', 'Ricarica Satispay%')->sole();
        expect((float) $expense->amount)->toBe(10.0)
            ->and($expense->fee_amount)->toBeNull();

        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Rome'));
        $this->feed->gives([$debit, $fee]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());
        // The bank gives the same commission at the read after
        $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00', 'Europe/Rome'));
        resolve(SyncBankConnection::class)->handle($connection->refresh());

        $expense->refresh();
        expect((float) $expense->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and(Expense::query()->count())->toBe(1)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->matched_by)->toBe(BankMatchSource::Fee);
    });
});

test('a filter that pays with no commission keeps the planned fee once, whenever the commission arrives', function (bool $sameRead): void {
    insideBianchi(function () use ($sameRead): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Rome'));
        $connection = BankConnection::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]);
        $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
        $account = FinancialAccount::factory()->create(['family_id' => $connection->family_id, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1']);
        $satispay = PlannedExpense::factory()->create([
            'family_id' => $connection->family_id, 'title' => 'Ricarica Satispay', 'amount' => 10.0,
            'fee_amount' => 0.40, 'is_estimated' => false, 'start_date' => '2026-01-24', 'financial_account_id' => $account->id,
        ]);
        BankFilter::factory()->pays($satispay)->create(['family_id' => $connection->family_id, 'contains' => ['MAN.SAT0023101207360112900'], 'fee_mode' => BankFilterFee::None]);
        $debit = new BankEntry('debit-1', CarbonImmutable::parse('2026-11-24'), CarbonImmutable::parse('2026-11-24'), -10.0, 'EUR', attachBankFeeText(), null, true);
        $fee = new BankEntry('fee-1', CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-25'), -0.40, 'EUR', attachBankFeeText(true), null, true);

        if (! $sameRead) {
            $this->feed->gives([$debit]);
            resolve(SyncBankConnection::class)->handle($connection->refresh());
        }

        $this->feed->gives([$debit, $fee]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());
        // A second read changes nothing
        $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00', 'Europe/Rome'));
        resolve(SyncBankConnection::class)->handle($connection->refresh());

        // The payment is what the debit says, with the planned fee inside it (`SettleFromBank::pay()`): the
        // commission of the bank is not added on top, and stays to sort
        $expense = Expense::query()->sole();
        expect((float) $expense->amount)->toBe(10.0)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->expense_id)->toBeNull();
    });
})->with(['same read' => [true], 'later read' => [false]]);

test('a commission that arrives before its debit waits and is attached when the debit is linked', function (): void {
    insideBianchi(function (): void {
        $fee = attachBankFeeRow('2026-11-25', '-0.40', true);
        $debit = attachBankFeeRow('2026-11-24', '-10.00');
        $expense = Expense::factory()->create(['family_id' => $debit->family_id, 'amount' => 10, 'fee_amount' => null, 'date' => '2026-11-24']);

        // Alone, the commission has no expense to join
        resolve(AttachBankFee::class)->forNew([$fee]);
        expect($fee->refresh()->expense_id)->toBeNull();

        resolve(LinkRecordedMovement::class)->handle($debit, $expense);

        expect((float) $expense->refresh()->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and($fee->refresh()->matched_by)->toBe(BankMatchSource::Fee);
    });
});

test('a recorded expense of 10,40 linked to the debit takes the commission without adding it again', function (): void {
    insideBianchi(function (): void {
        attachBankFeeRow('2026-11-25', '-0.40', true);
        $debit = attachBankFeeRow('2026-11-24', '-10.00');
        $expense = Expense::factory()->create(['family_id' => $debit->family_id, 'amount' => 10.4, 'fee_amount' => null, 'date' => '2026-11-24']);

        resolve(LinkRecordedMovement::class)->handle($debit, $expense);

        expect((float) $expense->refresh()->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4);
    });
});

test('an expense edited by hand in between keeps the person\'s amount plus the fee', function (): void {
    insideBianchi(function (): void {
        [$debit, $expense] = attachBankFeeLinked();
        $expense->update(['amount' => 12]);
        $fee = attachBankFeeRow('2026-11-25', '-0.40', true);

        resolve(AttachBankFee::class)->forNew([$fee]);

        expect((float) $expense->refresh()->amount)->toBe(12.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and($debit->refresh()->expense_id)->toBe($expense->id);
    });
});

test('detaching never goes below zero', function (): void {
    insideBianchi(function (): void {
        [, $expense] = attachBankFeeLinked();
        $fee = attachBankFeeRow('2026-11-25', '-0.40', true);
        resolve(AttachBankFee::class)->handle($fee, $expense);
        // The person brought it down by hand meanwhile
        $expense->refresh()->update(['amount' => 0.2, 'fee_amount' => 0.1]);

        resolve(DetachBankFee::class)->handle($expense);

        $fee->refresh();
        expect((float) $expense->refresh()->amount)->toBe(0.0)
            ->and($expense->fee_amount)->toBeNull()
            ->and($fee->expense_id)->toBeNull()
            ->and($fee->matched_by)->toBeNull()
            ->and($fee->declined_at)->toBeNull();
    });
});

test('a commission recognised by the words of the filter that arrives at a later read joins the expense the filter created', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-12-24 10:00', 'Europe/Rome'));
        $connection = BankConnection::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]);
        $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
        $account = FinancialAccount::factory()->create(['family_id' => $connection->family_id, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1']);
        BankFilter::factory()->create([
            'family_id' => $connection->family_id, 'name' => 'Satispay', 'contains' => ['SATISPAY'], 'excludes' => ['COMMISSIONI'],
            'action' => BankFilterAction::Create, 'title' => 'Ricarica Satispay',
            'fee_mode' => BankFilterFee::Words, 'fee_contains' => ['COMMISSIONI', 'SATISPAY'], 'fee_excludes' => [],
        ]);
        $debit = new BankEntry('debit-1', CarbonImmutable::parse('2026-12-24'), null, -10.0, 'EUR', 'RICARICA SATISPAY', null, true);
        $fee = new BankEntry('fee-1', CarbonImmutable::parse('2026-12-25'), null, -0.40, 'EUR', 'COMMISSIONI SATISPAY', null, true);

        $this->feed->gives([$debit]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());
        $expense = Expense::query()->sole();
        expect((float) $expense->amount)->toBe(10.0);

        $this->travelTo(CarbonImmutable::parse('2026-12-25 10:00', 'Europe/Rome'));
        $this->feed->gives([$debit, $fee]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());

        $expense->refresh();
        expect((float) $expense->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and(Expense::query()->count())->toBe(1)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->expense_id)->toBe($expense->id)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->matched_by)->toBe(BankMatchSource::Fee);
    });
});

test('a later commission is not joined to a debit whose filter does not recognise it with words', function (): void {
    insideBianchi(function (): void {
        $filter = BankFilter::factory()->create([
            'family_id' => attachBankFeeAccount()->family_id, 'contains' => ['SATISPAY'], 'fee_mode' => BankFilterFee::None,
        ]);
        $expense = Expense::factory()->create(['family_id' => $filter->family_id, 'amount' => 10, 'fee_amount' => null, 'date' => '2026-11-24']);
        attachBankFeeRow('2026-11-24', '-10.00', false, ['text' => 'RICARICA SATISPAY', 'expense_id' => $expense->id, 'matched_by' => BankMatchSource::Rule, 'bank_filter_id' => $filter->id]);
        $fee = attachBankFeeRow('2026-11-25', '-0.40', false, ['text' => 'COMMISSIONI SATISPAY']);

        resolve(AttachBankFee::class)->forNew([$fee]);

        expect($fee->refresh()->expense_id)->toBeNull()
            ->and((float) $expense->refresh()->amount)->toBe(10.0);
    });
});

/**
 * The queries a piece of work runs, from the first lock of a bank row on.
 *
 * @param  Closure(): mixed  $work  the work
 * @return list<string>
 */
function attachBankFeeLocks(Closure $work): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    $start = array_find_key($queries, static fn (string $sql): bool => str_contains($sql, 'from `bank_transactions`') && str_contains($sql, 'for update'));

    return array_values(array_slice($queries, (int) $start));
}

/**
 * Where the first query that locks a table comes in a list of queries, the commission rows (`like`) apart.
 *
 * @param  list<string>  $queries  the queries
 * @param  string  $table  `fees`, `expenses` or `planned_expenses`
 */
function attachBankFeeLockAt(array $queries, string $table): ?int
{
    return array_find_key($queries, static fn (string $sql): bool => str_contains($sql, 'for update') && match ($table) {
        'fees' => str_contains($sql, 'from `bank_transactions`') && str_contains($sql, 'like'),
        default => str_contains($sql, 'from `'.$table.'`'),
    });
}

test('linking a recorded expense, paying by a filter or saying yes lock the commission before the expense or the planned item', function (string $path): void {
    insideBianchi(function () use ($path): void {
        $debit = attachBankFeeRow('2026-11-24', '-10.00');
        attachBankFeeRow('2026-11-25', '-0.40', true);
        $planned = PlannedExpense::factory()->create([
            'family_id' => $debit->family_id, 'title' => 'Ricarica Satispay', 'amount' => 10.0, 'fee_amount' => null,
            'is_estimated' => false, 'start_date' => '2026-01-24', 'financial_account_id' => null,
        ]);
        BankFilter::factory()->pays($planned)->create(['family_id' => $debit->family_id, 'contains' => ['MAN.SAT0023101207360112900'], 'fee_mode' => BankFilterFee::Auto]);
        // Recorded by hand as the bank charged it, so a yes proposes it
        $expense = Expense::factory()->create(['family_id' => $debit->family_id, 'amount' => 10.40, 'fee_amount' => null, 'date' => '2026-11-24']);
        $marco = User::query()->where('email', 'marco@example.com')->sole();

        $queries = attachBankFeeLocks(fn (): mixed => match ($path) {
            'link' => resolve(LinkRecordedMovement::class)->handle($debit, $expense),
            'filter' => resolve(MatchBankTransactions::class)->handle([$debit]),
            'yes' => resolve(AcceptBankProposal::class)->handle($debit, 'expense:'.$expense->id, $marco),
        });
        $target = attachBankFeeLockAt($queries, $path === 'filter' ? 'planned_expenses' : 'expenses');
        expect(attachBankFeeLockAt($queries, 'fees'))->not->toBeNull()
            ->and($target)->not->toBeNull()
            ->and(attachBankFeeLockAt($queries, 'fees'))->toBeLessThan($target);
    });
})->with(['link', 'filter', 'yes']);
