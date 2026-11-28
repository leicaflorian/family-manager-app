<?php

declare(strict_types=1);

use App\Actions\Budget\RecordPayment;
use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-26 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/** The BancoPosta account of the Bianchi, made once. */
function bankFilterPagesAccount(): FinancialAccount
{
    return insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('name', 'Conto BancoPosta')->first()
        ?? FinancialAccount::factory()->create(['family_id' => test()->marco->family_id, 'name' => 'Conto BancoPosta', 'last_four_digits' => '3307']));
}

/**
 * A booked transaction of the Bianchi on BancoPosta.
 *
 * @param  array<string, mixed>  $state  more columns
 */
function bankFilterPagesRow(string $text, string $amount, string $day, array $state = []): BankTransaction
{
    $account = bankFilterPagesAccount();

    return insideBianchi(fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => test()->marco->family_id,
        'financial_account_id' => $account->id,
        'text' => $text,
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$state,
    ]));
}

/**
 * A filter of the Bianchi.
 *
 * @param  array<string, mixed>  $state  the columns
 */
function bankFilterPagesFilter(array $state = []): BankFilter
{
    return insideBianchi(fn (): BankFilter => BankFilter::factory()->create(['family_id' => test()->marco->family_id, ...$state]));
}

/**
 * The form of a filter that creates «Ricarica Satispay» for SATISPAY.
 *
 * @param  array<string, mixed>  $fields  what to change
 * @return array<string, mixed>
 */
function bankFilterPagesForm(array $fields = []): array
{
    return [
        'name' => 'Satispay',
        'contains' => ['SATISPAY'],
        'excludes' => ['COMMISSIONI'],
        'direction' => 'expense',
        'financial_account_id' => null,
        'amount_min' => null,
        'amount_max' => null,
        'pattern' => null,
        'fee_mode' => 'none',
        'fee_contains' => [],
        'fee_excludes' => [],
        'action' => 'create',
        'title' => 'Ricarica Satispay',
        'title_with_month' => false,
        ...$fields,
    ];
}

/**
 * Saves a new filter.
 *
 * @param  array<string, mixed>  $fields  what to change in the form
 */
function bankFilterPagesStore(array $fields = []): TestResponse
{
    return test()->post(route('budget.bank.filters.store'), bankFilterPagesForm($fields));
}

/** The monthly Luce of the Bianchi, from January 2026. */
function bankFilterPagesLuce(array $state = []): PlannedExpense
{
    return insideBianchi(fn (): PlannedExpense => PlannedExpense::factory()->create([
        'family_id' => test()->marco->family_id, 'title' => 'Luce', 'amount' => 72, 'fee_amount' => null,
        'start_date' => '2026-01-05', 'financial_account_id' => null, ...$state,
    ]));
}

test('saving settles what is to sort and says how many', function (): void {
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-20.00', '2026-11-17');
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-30.00', '2026-11-10');
    bankFilterPagesRow('POS ESSELUNGA', '-40.00', '2026-11-10');

    bankFilterPagesStore()
        ->assertRedirect(route('budget.bank.index', ['show' => 'filters']))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Filter saved: it sorted 3 movements.');

    $filter = insideBianchi(fn (): BankFilter => BankFilter::query()->sole());
    expect($filter->name)->toBe('Satispay')
        ->and($filter->action)->toBe(BankFilterAction::Create)
        ->and(insideBianchi(fn (): int => Expense::query()->where('title', 'Ricarica Satispay')->count()))->toBe(3)
        ->and(insideBianchi(fn (): int => BankTransaction::query()->where('bank_filter_id', $filter->id)->count()))->toBe(3);
});

test('changing a filter saves it and settles what it takes now', function (): void {
    $filter = bankFilterPagesFilter(['name' => 'Satispay', 'contains' => ['NOTHINGLIKETHIS']]);
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    $this->put(route('budget.bank.filters.update', $filter), bankFilterPagesForm(['name' => 'Satispay nuovo']))
        ->assertRedirect(route('budget.bank.index', ['show' => 'filters']))
        ->assertInertiaFlash('toast.message', 'Filter saved: it sorted 1 movement.');

    expect($filter->refresh()->name)->toBe('Satispay nuovo')
        ->and($filter->contains)->toBe(['SATISPAY']);
});

test('a filter without words is refused', function (array $words): void {
    bankFilterPagesStore(['contains' => $words])->assertSessionHasErrors('contains');

    expect(insideBianchi(fn (): int => BankFilter::query()->count()))->toBe(0);
})->with([
    'no word' => [[]],
    'words of two letters' => [['SA', 'PO']],
    'only symbols' => [['***']],
]);

test('an advanced filter whose pattern is not valid is refused', function (): void {
    bankFilterPagesStore(['contains' => [], 'pattern' => '.*'])->assertSessionHasErrors('pattern');
});

test('an amount range from more to less is refused', function (): void {
    bankFilterPagesStore(['amount_min' => '50', 'amount_max' => '10'])->assertSessionHasErrors('amount_max');
});

test('one that conflicts with another filter is refused and names it', function (): void {
    bankFilterPagesFilter(['name' => 'Satispay ricariche', 'contains' => ['SATISPAY']]);
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    $response = bankFilterPagesStore(['contains' => ['SATISPAY', 'DOMICILIAZIONE']])->assertSessionHasErrors('contains');

    expect(session('errors')->first('contains'))->toContain('Satispay ricariche')
        ->and(insideBianchi(fn (): int => BankFilter::query()->count()))->toBe(1);
});

test('a movement already sorted that another filter takes is no conflict', function (): void {
    bankFilterPagesFilter(['name' => 'Satispay ricariche', 'contains' => ['SATISPAY']]);
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24', ['ignored_at' => now()]);

    bankFilterPagesStore(['contains' => ['SATISPAY', 'DOMICILIAZIONE']])->assertSessionHasNoErrors();
});

test('one that pays needs its planned item', function (): void {
    bankFilterPagesStore(['action' => 'pay', 'planned_id' => null])->assertSessionHasErrors('planned_id');
});

test('one that pays settles the due date of its planned item', function (): void {
    $luce = bankFilterPagesLuce();
    bankFilterPagesRow('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

    bankFilterPagesStore(['name' => 'Luce', 'contains' => ['EDISON'], 'action' => 'pay', 'planned_id' => $luce->id, 'title' => null])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Filter saved: it sorted 1 movement.');

    $filter = insideBianchi(fn (): BankFilter => BankFilter::query()->sole());
    expect($filter->planned_expense_id)->toBe($luce->id)
        ->and($filter->planned_income_id)->toBeNull()
        ->and($filter->title)->toBeNull();
});

test('one that is a transfer needs its account', function (): void {
    bankFilterPagesStore(['action' => 'transfer', 'transfer_account_id' => null])->assertSessionHasErrors('transfer_account_id');
});

test('a member cannot save a filter that pays', function (): void {
    $luce = bankFilterPagesLuce();
    $this->actingAs(User::factory()->for($this->marco->family)->create(['role' => Role::Member]));

    bankFilterPagesStore(['action' => 'pay', 'planned_id' => $luce->id])->assertForbidden();
    bankFilterPagesStore(['action' => 'ignore'])->assertRedirect(route('budget.bank.index', ['show' => 'filters']));

    $filter = insideBianchi(fn (): BankFilter => BankFilter::query()->sole());
    $this->put(route('budget.bank.filters.update', $filter), bankFilterPagesForm(['action' => 'pay', 'planned_id' => $luce->id]))->assertForbidden();

    expect($filter->refresh()->action)->toBe(BankFilterAction::Ignore);
});

test('deleting a filter leaves its expenses and unlinks nothing', function (): void {
    $filter = bankFilterPagesFilter(['name' => 'Satispay', 'contains' => ['SATISPAY']]);
    $expense = insideBianchi(fn (): Expense => Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Ricarica Satispay', 'amount' => '10.00', 'date' => '2026-11-24']));
    $row = bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24', [
        'expense_id' => $expense->id, 'matched_by' => BankMatchSource::Rule, 'bank_filter_id' => $filter->id,
    ]);

    $this->delete(route('budget.bank.filters.destroy', $filter))
        ->assertRedirect(route('budget.bank.index', ['show' => 'filters']))
        ->assertInertiaFlash('toast.message', 'Filter deleted. What it sorted stays as it is.');

    $row->refresh();
    expect(insideBianchi(fn (): int => BankFilter::query()->count()))->toBe(0)
        ->and(insideBianchi(fn (): bool => Expense::query()->whereKey($expense->id)->exists()))->toBeTrue()
        ->and($row->expense_id)->toBe($expense->id)
        ->and($row->matched_by)->toBe(BankMatchSource::Rule)
        ->and($row->bank_filter_id)->toBeNull();
});

test('the Filters tab lists the filters with how many movements each took and what is missing', function (): void {
    $luce = bankFilterPagesLuce(['end_date' => '2026-06-05']);
    $pays = bankFilterPagesFilter(['name' => 'Luce', 'contains' => ['EDISON'], 'action' => BankFilterAction::Pay, 'planned_expense_id' => $luce->id, 'financial_account_id' => bankFilterPagesAccount()->id]);
    $category = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Varie']));
    $organization = insideBianchi(fn (): Organization => Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Satispay']));
    $creates = bankFilterPagesFilter(['name' => 'Satispay', 'contains' => ['SATISPAY'], 'category_id' => $category->id, 'organization_id' => $organization->id]);
    $libretto = insideBianchi(fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Libretto postale', 'last_four_digits' => null]));
    bankFilterPagesFilter(['name' => 'Dal libretto', 'contains' => ['POSTAGIRO'], 'direction' => 'income', 'action' => BankFilterAction::Transfer, 'transfer_account_id' => $libretto->id, 'title' => null]);
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24', ['ignored_at' => now(), 'bank_filter_id' => $creates->id]);
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-20.00', '2026-11-17', ['ignored_at' => now(), 'bank_filter_id' => $creates->id]);
    // Out of the last 90 days: not counted
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-30.00', '2026-07-01', ['ignored_at' => now(), 'bank_filter_id' => $creates->id]);

    $this->get(route('budget.bank.index', ['show' => 'filters']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/BankTransactions')
            ->where('show', 'filters')
            ->has('bank_filters', 3)
            ->where('bank_filters.0.name', 'Dal libretto')
            ->where('bank_filters.0.transfer_account', 'Libretto postale')
            ->where('bank_filters.0.category', null)
            ->where('bank_filters.1.id', $pays->id)
            ->where('bank_filters.1.name', 'Luce')
            ->where('bank_filters.1.taken', 0)
            ->where('bank_filters.1.account', 'Conto BancoPosta ·· 3307')
            ->where('bank_filters.1.planned', ['kind' => 'expense', 'id' => $luce->id, 'title' => 'Luce'])
            ->where('bank_filters.1.missing', 'The planned expense ended on 05/06/2026.')
            ->where('bank_filters.1.can', ['update' => true, 'delete' => true])
            ->where('bank_filters.2.name', 'Satispay')
            ->where('bank_filters.2.taken', 2)
            ->where('bank_filters.2.missing', null)
            ->where('bank_filters.2.category', 'Varie')
            ->where('bank_filters.2.organization', 'Satispay')
            ->where('bank_filters.2.transfer_account', null)
            ->where('bank_filters.2.contains', ['SATISPAY']));
});

test('the other tabs do not read the filters', function (): void {
    bankFilterPagesFilter();

    $this->get(route('budget.bank.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('bank_filters', []));
});

test('the form of a new filter from a movement takes its words, direction and account', function (): void {
    $row = bankFilterPagesRow('DOMICILIAZIONE ADDEBITO DIRETTO SEPA SATISPAY EUROPE CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900', '-10.00', '2026-11-24', ['counterparty' => 'Satispay Europe S.A.']);

    $this->get(route('budget.bank.filters.create', ['from' => $row->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/BankFilter')
            ->where('filter', null)
            ->where('draft.id', null)
            ->where('draft.name', 'Satispay Europe S.A.')
            ->where('draft.contains', ['MAN.SAT0023101207360112900'])
            ->where('draft.excludes', ['COMMISSIONI'])
            ->where('draft.direction', 'expense')
            ->where('draft.financial_account_id', bankFilterPagesAccount()->id)
            ->has('preview.rows', 1)
            ->where('preview.rows.0.transaction.id', $row->id)
            ->has('options.categories')
            ->has('options.accounts')
            ->has('options.transfer_accounts')
            ->has('options.planned')
            ->where('can_pay', true)
            ->where('ai', false));
});

test('the form of the filter of a planned item pays it', function (): void {
    $luce = bankFilterPagesLuce();

    $this->get(route('budget.bank.filters.create', ['planned' => 'expense:'.$luce->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('draft.action', 'pay')
            ->where('draft.name', 'Luce')
            ->where('draft.direction', 'expense')
            ->where('draft.planned_expense_id', $luce->id)
            ->where('draft.planned', ['kind' => 'expense', 'id' => $luce->id, 'title' => 'Luce']));
});

test('a member gets no planned item to pay on the form', function (): void {
    $luce = bankFilterPagesLuce();
    $this->actingAs(User::factory()->for($this->marco->family)->create(['role' => Role::Member]));

    $this->get(route('budget.bank.filters.create', ['planned' => 'expense:'.$luce->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('draft', null)
            ->where('can_pay', false));
});

test('editing shows the filter and what it takes', function (): void {
    $category = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::factory()->create(['family_id' => $this->marco->family_id]));
    $filter = bankFilterPagesFilter(['name' => 'Satispay', 'contains' => ['SATISPAY'], 'category_id' => $category->id, 'card_type' => null]);
    bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    $this->get(route('budget.bank.filters.edit', $filter))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/BankFilter')
            ->where('filter.id', $filter->id)
            ->where('filter.category_id', $category->id)
            ->where('filter.action', 'create')
            ->where('filter.fee_mode', 'none')
            ->where('draft', null)
            ->where('preview.rows.0.will', 'create')
            ->where('preview.rows.0.taken_by', null)
            ->where('preview.conflicts', 0));
});

test('another family\'s filter gives 404', function (): void {
    $rossi = registerFamily('Rossi', 'luca@example.com');
    $theirs = insideFamilyOf($rossi, fn (): BankFilter => BankFilter::factory()->create(['family_id' => $rossi->family_id]));
    $category = insideFamilyOf($rossi, fn (): ExpenseCategory => ExpenseCategory::factory()->create(['family_id' => $rossi->family_id]));

    $this->get(route('budget.bank.filters.edit', $theirs))->assertNotFound();
    $this->put(route('budget.bank.filters.update', $theirs), bankFilterPagesForm())->assertNotFound();
    $this->delete(route('budget.bank.filters.destroy', $theirs))->assertNotFound();
    bankFilterPagesStore(['category_id' => $category->id, 'contains' => []])->assertNotFound();

    expect(insideFamilyOf($rossi, fn (): int => BankFilter::query()->count()))->toBe(1);
});

test('an id of another family sent as a JSON float gives 404 and saves nothing', function (string $field): void {
    $rossi = registerFamily('Rossi', 'luca@example.com');
    $id = insideFamilyOf($rossi, fn (): int => match ($field) {
        'category_id' => ExpenseCategory::factory()->create(['family_id' => $rossi->family_id])->id,
        'organization_id' => Organization::factory()->create(['family_id' => $rossi->family_id])->id,
        'person_id' => Person::factory()->create(['family_id' => $rossi->family_id])->id,
    });

    $this->postJson(route('budget.bank.filters.store'), bankFilterPagesForm([$field => (float) $id]), [], JSON_PRESERVE_ZERO_FRACTION)->assertNotFound();

    expect(insideBianchi(fn (): int => BankFilter::query()->count()))->toBe(0);
})->with(['category_id', 'organization_id', 'person_id']);

test('a card of another family gives 404', function (): void {
    $rossi = registerFamily('Rossi', 'luca@example.com');
    $theirs = insideFamilyOf($rossi, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $rossi->family_id]));

    bankFilterPagesStore(['card' => 'vehicle:'.$theirs->id])->assertNotFound();

    expect(insideBianchi(fn (): int => BankFilter::query()->count()))->toBe(0);
});

test('the preview says what saving does', function (string $case): void {
    if ($case === 'pay') {
        $luce = bankFilterPagesLuce();
        $deadline = insideBianchi(fn () => resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-10-05')));
        insideBianchi(fn () => resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-10-05'), 72.0, null, null));
        bankFilterPagesRow('ADDEBITO SDD EDISON ENERGIA', '-72.00', '2026-10-05');
        bankFilterPagesRow('ADDEBITO SDD EDISON ENERGIA', '-72.00', '2026-11-04');
        bankFilterPagesRow('ADDEBITO SDD EDISON ENERGIA', '-72.00', '2026-11-06');
        $form = ['name' => 'Luce', 'contains' => ['EDISON'], 'action' => 'pay', 'planned_id' => $luce->id, 'title' => null];
    } else {
        insideBianchi(fn (): Expense => Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Ricarica a mano', 'amount' => '10.00', 'date' => '2026-11-20']));
        bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-17');
        bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');
        bankFilterPagesRow('DOMICILIAZIONE SATISPAY', '-25.00', '2026-11-10');
        $form = [];
    }

    $summary = $this->postJson(route('budget.bank.filters.preview'), bankFilterPagesForm($form))->assertOk()->json('summary');
    bankFilterPagesStore($form)->assertSessionHasNoErrors();

    $filter = insideBianchi(fn (): BankFilter => BankFilter::query()->sole());
    $made = insideBianchi(fn (): int => BankTransaction::query()->where('bank_filter_id', $filter->id)->where('matched_by', BankMatchSource::Rule)->count());
    $linked = insideBianchi(fn (): int => BankTransaction::query()->where('bank_filter_id', $filter->id)->where('matched_by', BankMatchSource::Duplicate)->count());

    expect($summary['pay'] + $summary['create'])->toBe($made)
        ->and($summary['link'])->toBe($linked)
        ->and($summary['link'])->toBeGreaterThan(0)
        ->and($made)->toBeGreaterThan(0);
})->with(['pay', 'create']);

test('the pages are 404 while the feed is off', function (): void {
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->get(route('budget.bank.filters.create'))->assertNotFound();
    bankFilterPagesStore()->assertNotFound();
    $this->postJson(route('budget.bank.filters.preview'), bankFilterPagesForm())->assertNotFound();
});

test('the form from a movement gives it as the example, and asks the AI by itself only with ai=1', function (): void {
    $row = bankFilterPagesRow('DOMICILIAZIONE ADDEBITO DIRETTO SEPA SATISPAY EUROPE', '-10.00', '2026-11-24', ['counterparty' => 'Satispay Europe S.A.']);

    $this->get(route('budget.bank.filters.create', ['from' => $row->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('example_ids', [$row->id])
            ->where('ask_ai', false));

    $this->get(route('budget.bank.filters.create', ['from' => $row->id, 'ai' => 1]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('ask_ai', true));
});

test('a saved filter and a form from nowhere never ask the AI by themselves', function (): void {
    $filter = bankFilterPagesFilter();

    $this->get(route('budget.bank.filters.create', ['ai' => 1]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('example_ids', [])->where('ask_ai', false));
    $this->get(route('budget.bank.filters.edit', [$filter, 'ai' => 1]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('ask_ai', false));
});
