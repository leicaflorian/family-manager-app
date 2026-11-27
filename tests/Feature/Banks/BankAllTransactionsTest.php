<?php

declare(strict_types=1);

use App\Enums\BankMatchSource;
use App\Enums\BankTransactionStatus;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/**
 * A connection of the family of an account with an account of its own.
 *
 * @param  string  $name  the name of the account
 */
function bankAllTransactionsConnection(User $user, string $name = 'Conto BancoPosta'): BankConnection
{
    return insideFamilyOf($user, function () use ($user, $name): BankConnection {
        $connection = BankConnection::factory()->create(['family_id' => $user->family_id, 'institution_name' => 'Banca '.$name]);
        FinancialAccount::factory()->create([
            'family_id' => $user->family_id,
            'bank_connection_id' => $connection->id,
            'bank_account_uid' => 'uid-'.$connection->id,
            'name' => $name,
            'last_four_digits' => '3307',
        ]);

        return $connection;
    });
}

/**
 * A transaction of the account of a connection.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankAllTransactionsRow(BankConnection $connection, array $state = []): BankTransaction
{
    return insideFamilyOf(test()->marco, fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => $connection->family_id,
        'bank_connection_id' => $connection->id,
        'financial_account_id' => $connection->accounts()->firstOrFail()->id,
        ...$state,
    ]));
}

/**
 * A row linked to a new expense.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankAllTransactionsLinked(BankConnection $connection, BankMatchSource $by = BankMatchSource::Rule, array $state = []): BankTransaction
{
    $expense = insideFamilyOf(test()->marco, fn (): Expense => Expense::factory()->create(['family_id' => $connection->family_id, 'title' => 'Bolletta luce']));

    return bankAllTransactionsRow($connection, ['expense_id' => $expense->id, 'matched_by' => $by, ...$state]);
}

/**
 * A partial reload of the list with a new search, as Inertia asks it: the rows are reset.
 *
 * @param  string  $url  the address
 */
function bankAllTransactionsReset(string $url): TestResponse
{
    return test()->getJson($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => 'budget/BankTransactions',
        'X-Inertia-Partial-Data' => 'rows,next,total',
        'X-Inertia-Reset' => 'rows',
    ])->assertOk();
}

it('lists every transaction, linked ones too, newest first', function (): void {
    $connection = bankAllTransactionsConnection($this->marco);
    bankAllTransactionsRow($connection, ['text' => 'OLD', 'booked_on' => '2026-11-01']);
    bankAllTransactionsRow($connection, ['text' => 'ASIDE', 'booked_on' => '2026-11-03', 'ignored_at' => now()]);
    bankAllTransactionsLinked($connection, state: ['text' => 'LINKED', 'booked_on' => '2026-11-04']);
    bankAllTransactionsRow($connection, ['text' => 'WAITING', 'booked_on' => null, 'value_on' => '2026-11-06', 'status' => BankTransactionStatus::Pending]);

    $this->get(route('budget.bank.index', ['show' => 'all']))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('show', 'all')
        ->has('rows', 4)
        ->where('rows.0.text', 'WAITING')
        ->where('rows.0.status', 'pending')
        ->where('rows.0.bank_status', 'pending')
        ->where('rows.1.text', 'LINKED')
        ->where('rows.1.status', 'linked')
        ->where('rows.2.text', 'ASIDE')
        ->where('rows.2.status', 'ignored')
        ->where('rows.3.text', 'OLD')
        ->where('rows.3.status', 'unsorted')
        ->where('rows.3.bank_status', 'booked')
        ->where('rows.3.link', null)
        ->has('rows.3.provider_id')
        ->has('rows.3.read_at')
        ->where('next', null)
        ->where('accounts', fn ($accounts): bool => count($accounts) === 1 && $accounts[0]['name'] === 'Conto BancoPosta ·· 3307'));
});

it('a linked row says the expense, its address and how it was linked', function (): void {
    $connection = bankAllTransactionsConnection($this->marco);
    $row = bankAllTransactionsLinked($connection, BankMatchSource::Rule);
    $income = insideFamilyOf($this->marco, fn (): Income => Income::factory()->create(['family_id' => $connection->family_id, 'title' => 'Stipendio']));
    bankAllTransactionsRow($connection, ['income_id' => $income->id, 'matched_by' => BankMatchSource::Created, 'amount' => '1500.00', 'booked_on' => '2026-01-01']);

    $this->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.link.kind', 'expense')
        ->where('rows.0.link.id', $row->expense_id)
        ->where('rows.0.link.title', 'Bolletta luce')
        ->where('rows.0.link.url', route('budget.movements.show', ['kind' => 'expense', 'id' => $row->expense_id], absolute: false))
        ->where('rows.0.link.how', 'rule')
        ->where('rows.0.link.filter_id', null)
        ->where('rows.0.link.filter_name', null)
        ->where('rows.1.link.kind', 'income')
        ->where('rows.1.link.title', 'Stipendio')
        ->where('rows.1.link.how', 'created')
        ->where('rows.1.link.undo_url', null));
});

it('filters by account, status, month, direction and words', function (): void {
    $connection = bankAllTransactionsConnection($this->marco, 'Conto A');
    $other = bankAllTransactionsConnection($this->marco, 'Conto B');
    bankAllTransactionsRow($connection, ['text' => 'EDISON ENERGIA', 'booked_on' => '2026-11-05', 'amount' => '-72.40']);
    bankAllTransactionsRow($connection, ['text' => 'STIPENDIO NOVEMBRE', 'booked_on' => '2026-11-27', 'amount' => '1500.00']);
    bankAllTransactionsRow($connection, ['text' => 'EDISON OTTOBRE', 'booked_on' => '2026-10-05', 'amount' => '-60.00']);
    bankAllTransactionsRow($other, ['text' => 'EDISON ALTRO CONTO', 'booked_on' => '2026-11-06', 'amount' => '-10.00']);
    bankAllTransactionsRow($connection, ['text' => 'SET ASIDE', 'booked_on' => '2026-11-07', 'ignored_at' => now()]);
    bankAllTransactionsLinked($connection, state: ['text' => 'LINKED ONE', 'booked_on' => '2026-11-08']);
    $accountId = $connection->accounts()->firstOrFail()->id;
    $texts = fn (array $query): array => collect($this->get(route('budget.bank.index', ['show' => 'all', ...$query]))->assertOk()->viewData('page')['props']['rows'])->pluck('text')->sort()->values()->all();

    expect($texts(['account' => $accountId, 'month' => '2026-11', 'q' => 'edison']))->toBe(['EDISON ENERGIA'])
        ->and($texts(['direction' => 'income']))->toBe(['STIPENDIO NOVEMBRE'])
        ->and($texts(['status' => 'ignored']))->toBe(['SET ASIDE'])
        ->and($texts(['status' => 'linked']))->toBe(['LINKED ONE'])
        ->and($texts(['status' => 'unsorted', 'month' => '2026-10']))->toBe(['EDISON OTTOBRE'])
        ->and($texts(['q' => 'edison energia']))->toBe(['EDISON ENERGIA'])
        ->and($texts(['q' => 'edison altro']))->toBe(['EDISON ALTRO CONTO'])
        ->and($texts(['status' => 'fee']))->toBe([]);
});

it('the total counts and sums what the filters find, not the page', function (): void {
    $connection = bankAllTransactionsConnection($this->marco);
    insideFamilyOf($this->marco, fn () => BankTransaction::factory()->count(52)->create([
        'family_id' => $connection->family_id,
        'bank_connection_id' => $connection->id,
        'financial_account_id' => $connection->accounts()->firstOrFail()->id,
        'amount' => '-10.10',
    ]));
    bankAllTransactionsRow($connection, ['text' => 'BIG', 'amount' => '-100.00']);

    $this->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('rows', 50)
        ->where('next', 2)
        ->where('total', ['count' => 53, 'sum' => -625.2]));
    $this->get(route('budget.bank.index', ['show' => 'all', 'q' => 'big']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('total', ['count' => 1, 'sum' => -100])
        ->where('filters.q', 'big'));
});

it('a reset search with a new filter replaces the rows', function (): void {
    $connection = bankAllTransactionsConnection($this->marco);
    bankAllTransactionsRow($connection, ['text' => 'EDISON']);
    bankAllTransactionsRow($connection, ['text' => 'COOP']);

    $response = bankAllTransactionsReset(route('budget.bank.index', ['show' => 'all', 'q' => 'coop']));

    expect(collect($response->json('props.rows'))->pluck('text')->all())->toBe(['COOP'])
        ->and($response->json('props.total.count'))->toBe(1)
        ->and($response->json('mergeProps'))->toBeNull();
});

it('the undo address is given only to who can delete the payment', function (): void {
    $connection = bankAllTransactionsConnection($this->marco);
    $row = bankAllTransactionsLinked($connection, BankMatchSource::Assigned);
    bankAllTransactionsLinked($connection, BankMatchSource::Duplicate, ['booked_on' => '2026-01-01']);
    $undo = route('budget.bank.undo', ['transaction' => $row->id], absolute: false);

    $this->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.link.undo', 'delete')
        ->where('rows.0.link.undo_url', $undo)
        ->where('rows.1.link.undo', null)
        ->where('rows.1.link.undo_url', null));

    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member)->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.link.how', 'assigned')
        ->where('rows.0.link.undo', null)
        ->where('rows.0.link.undo_url', null));
});

it('never shows another family\'s transactions', function (): void {
    $other = registerFamily('Rossi', 'rossi@example.com');
    $other->family->forceFill(['bank_feed_enabled' => true])->save();
    $theirs = bankAllTransactionsConnection($other);
    insideFamilyOf($other, fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => $other->family_id,
        'bank_connection_id' => $theirs->id,
        'financial_account_id' => $theirs->accounts()->firstOrFail()->id,
        'text' => 'SECRET',
    ]));
    $mine = bankAllTransactionsConnection($this->marco);
    bankAllTransactionsRow($mine, ['text' => 'MINE']);

    $this->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('rows', 1)
        ->where('rows.0.text', 'MINE')
        ->where('total.count', 1)
        ->has('accounts', 1));
    $theirAccount = insideFamilyOf($other, fn (): int => $theirs->accounts()->firstOrFail()->id);
    $this->get(route('budget.bank.index', ['show' => 'all', 'account' => $theirAccount]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('rows', 0));
});
