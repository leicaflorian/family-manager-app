<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Support\Banks\BankMatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Pest\Expectation;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->laura = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member]);
});

/**
 * A transaction of the Bianchi still to sort.
 *
 * @param  array<string, mixed>  $attributes  what differs from a booked debit of the day
 */
function bankSortOne(array $attributes = []): BankTransaction
{
    return insideBianchi(fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'amount' => '-20.00', 'booked_on' => '2026-11-05', 'value_on' => '2026-11-05', ...$attributes,
    ]));
}

test('ignoring hides the transaction and the count drops', function (): void {
    $transaction = bankSortOne();
    expect(insideBianchi(fn (): int => BankTransaction::query()->unsorted()->count()))->toBe(1);

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();

    insideBianchi(function () use ($transaction): void {
        expect($transaction->refresh()->ignored_at)->not->toBeNull()
            ->and(BankTransaction::query()->unsorted()->count())->toBe(0)
            ->and(BankTransaction::query()->ignored()->count())->toBe(1);
    });
});

test('restoring brings it back', function (): void {
    $transaction = bankSortOne(['ignored_at' => now()]);

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/ignore")->assertRedirect();

    insideBianchi(function () use ($transaction): void {
        expect($transaction->refresh()->ignored_at)->toBeNull()
            ->and(BankTransaction::query()->unsorted()->count())->toBe(1);
    });
});

test('a linked transaction cannot be ignored', function (): void {
    $transaction = insideBianchi(function (): BankTransaction {
        $expense = Expense::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]);

        return bankSortOne(['expense_id' => $expense->id]);
    });

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertStatus(422);

    insideBianchi(fn (): Expectation => expect($transaction->refresh()->ignored_at)->toBeNull());
});

test('a member can ignore and restore', function (): void {
    $transaction = bankSortOne();

    $this->actingAs($this->laura)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn () => expect($transaction->refresh()->ignored_at)->not->toBeNull());

    $this->actingAs($this->laura)->delete("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn (): Expectation => expect($transaction->refresh()->ignored_at)->toBeNull());
});

test('ignoring twice and restoring one that is not ignored change nothing', function (): void {
    $transaction = bankSortOne();

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn (): Expectation => expect($transaction->refresh()->ignored_at)->toBeNull());

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    $first = insideBianchi(fn () => $transaction->refresh()->ignored_at);
    Date::setTestNow(CarbonImmutable::parse('2026-11-06 07:00', 'Europe/Rome'));
    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn (): Expectation => expect($transaction->refresh()->ignored_at?->equalTo($first))->toBeTrue());
});

test("another family's transaction is 404", function (): void {
    $transaction = bankSortOne();
    $other = registerFamily('Rossi', 'rossi@example.com');
    $other->family->forceFill(['bank_feed_enabled' => true])->save();

    $this->actingAs($other)->post("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
    $this->actingAs($other)->delete("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
    insideBianchi(fn (): Expectation => expect($transaction->refresh()->ignored_at)->toBeNull());
});

test('ignoring answers 404 while the feed is off', function (): void {
    $transaction = bankSortOne();
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
});

/**
 * A planned expense of the Bianchi, monthly from a day, without a rule unless one is given.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankSortPlanned(string $title, string $start = '2026-01-05', array $attributes = []): PlannedExpense
{
    return insideBianchi(fn (): PlannedExpense => PlannedExpense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => $title, 'amount' => 72.0, 'fee_amount' => null, 'is_estimated' => false, 'bank_match' => null,
        'start_date' => $start, 'financial_account_id' => null, ...$attributes,
    ]));
}

/** The body of a new movement as the form sends it. */
function bankSortBody(BankTransaction $transaction, array $more = []): array
{
    return [
        'kind' => $transaction->kind(), 'title' => 'Bolletta luce', 'amount' => 72.4, 'date' => '2026-11-05',
        'financial_account_id' => $transaction->financial_account_id, 'bank_transaction_id' => $transaction->id,
        ...($transaction->kind() === 'income' ? ['type' => 'other'] : []),
        ...$more,
    ];
}

test('the new expense form starts from the bank transaction', function (): void {
    $transaction = bankSortOne(['amount' => '-72.40', 'text' => 'ADDEBITO DIRETTO SDD EDISON', 'counterparty' => 'Edison Energia SpA']);

    $this->actingAs($this->marco)->get(route('budget.movements.create', ['bank' => $transaction->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('budget/Movement')
        ->where('start.kind', 'expense')
        ->where('start.date', '2026-11-05')
        ->where('start.amount', 72.4)
        ->where('start.title', 'Edison Energia SpA')
        ->where('start.financial_account_id', (string) $transaction->financial_account_id)
        ->where('start.bank_transaction_id', $transaction->id)
        ->where('back', '/budget/bank'));

    $transaction->forceFill(['counterparty' => null])->save();
    $this->get(route('budget.movements.create', ['bank' => $transaction->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('start.title', 'ADDEBITO DIRETTO SDD EDISON'));
});

test('saving it links the transaction as created and it leaves the list', function (): void {
    $transaction = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->laura)->post(route('budget.movements.store'), bankSortBody($transaction))->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($transaction): void {
        $expense = Expense::query()->where('title', 'Bolletta luce')->sole();
        $transaction->refresh();

        expect($transaction->expense_id)->toBe($expense->id)
            ->and($transaction->income_id)->toBeNull()
            ->and($transaction->matched_by)->toBe(BankMatchSource::Created)
            ->and(BankTransaction::query()->unsorted()->count())->toBe(0);
    });
});

test('the form of an income starts from a credit and saving links the income', function (): void {
    $transaction = bankSortOne(['amount' => '35.00', 'text' => 'BONIFICO DA ROSSI GIULIA', 'counterparty' => 'Rossi Giulia']);

    $this->actingAs($this->marco)->get(route('budget.movements.create', ['bank' => $transaction->id, 'kind' => 'expense']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('start.kind', 'income')
        ->where('start.amount', 35)
        ->where('start.title', 'Rossi Giulia'));

    $this->post(route('budget.movements.store'), bankSortBody($transaction, ['amount' => 35, 'title' => 'Rimborso cena']))->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($transaction): void {
        $income = Income::query()->where('title', 'Rimborso cena')->sole();

        expect($transaction->refresh()->income_id)->toBe($income->id)
            ->and($transaction->expense_id)->toBeNull()
            ->and($transaction->matched_by)->toBe(BankMatchSource::Created);
    });
});

test('a transaction of another family or not to sort in the address is ignored', function (): void {
    $foreign = BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id]);
    $linked = bankSortOne(['expense_id' => insideBianchi(fn (): int => Expense::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id])->id)]);
    $ignored = bankSortOne(['ignored_at' => now()]);
    $pending = bankSortOne(['status' => 'pending', 'booked_on' => null]);

    foreach ([$foreign->id, $linked->id, $ignored->id, $pending->id, 'abc', '99999999'] as $bank) {
        $this->actingAs($this->marco)->get(route('budget.movements.create', ['bank' => $bank]))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('start.bank_transaction_id', null)
            ->where('start.title', null)
            ->where('back', null));
    }
});

test('saving with a transaction that is not to sort, of another family or of the other kind creates nothing', function (): void {
    $foreign = BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id, 'amount' => '-72.40']);
    $linked = bankSortOne(['expense_id' => insideBianchi(fn (): int => Expense::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id])->id)]);
    $debit = bankSortOne();

    $this->actingAs($this->marco);
    $expenses = insideBianchi(fn (): int => Expense::query()->count());

    $this->post(route('budget.movements.store'), bankSortBody($foreign))->assertSessionHasErrors('bank_transaction_id');
    $this->post(route('budget.movements.store'), bankSortBody($linked))->assertSessionHasErrors('bank_transaction_id');
    $this->post(route('budget.movements.store'), bankSortBody($debit, ['kind' => 'income', 'type' => 'other']))->assertSessionHasErrors('bank_transaction_id');

    expect(insideBianchi(fn (): int => Expense::query()->count()))->toBe($expenses)
        ->and(insideBianchi(fn (): int => Income::query()->count()))->toBe(0);
});

test('a transaction linked between the form and the save gives no second expense', function (): void {
    $transaction = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->marco)->post(route('budget.movements.store'), bankSortBody($transaction))->assertRedirect(route('budget.bank.index'));
    $this->post(route('budget.movements.store'), bankSortBody($transaction, ['title' => 'Again']))->assertSessionHasErrors('bank_transaction_id');

    expect(insideBianchi(fn (): int => Expense::query()->count()))->toBe(1);
});

test('with the feed off the address and the body of a movement ignore the transaction', function (): void {
    $transaction = bankSortOne();
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->actingAs($this->marco)->get(route('budget.movements.create', ['bank' => $transaction->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('start.bank_transaction_id', null));
    $this->post(route('budget.movements.store'), bankSortBody($transaction))->assertSessionHasErrors('bank_transaction_id');
});

test('assigning pays the closest open due date of the chosen planned expense', function (): void {
    $luce = bankSortPlanned('Luce');
    $transaction = bankSortOne(['amount' => '-72.40', 'booked_on' => '2026-11-03', 'value_on' => '2026-11-03']);

    $this->actingAs($this->laura)->post(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $luce->id])
        ->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($transaction, $luce): void {
        $expense = Expense::query()->where('planned_expense_id', $luce->id)->sole();
        $transaction->refresh();

        expect($expense->occurrence_date?->toDateString())->toBe('2026-11-05')
            ->and((float) $expense->amount)->toBe(72.4)
            ->and($expense->date?->toDateString())->toBe('2026-11-03')
            ->and($transaction->expense_id)->toBe($expense->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Assigned);
    });
});

test('assigning a credit pays a planned income and an income of a debit is refused', function (): void {
    $salary = insideBianchi(fn (): PlannedIncome => PlannedIncome::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'title' => 'Stipendio', 'amount' => 1500, 'start_date' => '2026-01-27', 'financial_account_id' => null, 'end_date' => null,
    ]));
    $credit = bankSortOne(['amount' => '1500.00', 'booked_on' => '2026-11-27', 'value_on' => '2026-11-27']);
    $debit = bankSortOne();

    $this->actingAs($this->marco)->postJson(route('budget.bank.assign', $debit), ['kind' => 'income', 'planned_id' => $salary->id])->assertStatus(422)->assertJsonValidationErrors('kind');

    Date::setTestNow(CarbonImmutable::parse('2026-11-28 07:00', 'Europe/Rome'));
    $this->post(route('budget.bank.assign', $credit), ['kind' => 'income', 'planned_id' => $salary->id])->assertRedirect();

    insideBianchi(function () use ($credit, $salary): void {
        $income = Income::query()->where('planned_income_id', $salary->id)->sole();

        expect($credit->refresh()->income_id)->toBe($income->id)
            ->and($credit->matched_by)->toBe(BankMatchSource::Assigned);
    });
});

test('assigning to a planned expense without open due dates is refused', function (): void {
    $later = bankSortPlanned('Bollo', '2027-06-01');
    $transaction = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->marco)->postJson(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $later->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['planned_id' => 'This planned expense has no due dates to pay.']);

    expect($transaction->refresh()->expense_id)->toBeNull()
        ->and(insideBianchi(fn (): int => Expense::query()->count()))->toBe(0);
});

test('assigning needs a planned item of the family that has not ended', function (): void {
    $foreign = PlannedExpense::factory()->create(['family_id' => Family::factory()->create()->id]);
    $ended = bankSortPlanned('Vecchio', '2025-01-05', ['end_date' => '2026-06-01']);
    $transaction = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->marco);
    // The address is resolved first: another family answers 404 even with a body that is not valid
    $this->postJson(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $foreign->id])->assertNotFound();
    $this->postJson(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => 999999])->assertNotFound();
    $this->postJson(route('budget.bank.assign', $transaction), ['kind' => 'nope', 'planned_id' => $ended->id])->assertStatus(404);
    $this->postJson(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $ended->id])->assertStatus(422)->assertJsonValidationErrors('planned_id');
    $this->postJson(route('budget.bank.assign', $transaction), ['kind' => 'expense'])->assertStatus(422);

    $other = BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id]);
    $this->postJson(route('budget.bank.assign', $other), ['kind' => 'expense', 'planned_id' => $ended->id])->assertNotFound();
});

test('assigning answers 404 while the feed is off', function (): void {
    $luce = bankSortPlanned('Luce');
    $transaction = bankSortOne();
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->actingAs($this->marco)->post(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $luce->id])->assertNotFound();
});

test('an admin lands on the planned form with the rule sheet ready when there is no rule yet, a member stays on the list', function (): void {
    $luce = bankSortPlanned('Luce');
    $withRule = bankSortPlanned('Gas', '2026-01-05', ['bank_match' => 'ENEL']);
    $first = bankSortOne(['amount' => '-72.40']);
    $second = bankSortOne(['amount' => '-72.40']);
    $third = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->marco)->post(route('budget.bank.assign', $first), ['kind' => 'expense', 'planned_id' => $luce->id])
        ->assertRedirect(route('budget.planned.edit', ['kind' => 'expense', 'id' => $luce->id, 'rule_from' => $first->id]));

    $this->post(route('budget.bank.assign', $second), ['kind' => 'expense', 'planned_id' => $withRule->id])->assertRedirect(route('budget.bank.index'));

    // Another due date for a member: the planned item has no rule, but she cannot change it
    $other = bankSortPlanned('Acqua', '2026-01-05');
    $this->actingAs($this->laura)->post(route('budget.bank.assign', $third), ['kind' => 'expense', 'planned_id' => $other->id])->assertRedirect(route('budget.bank.index'));
});

test('assigning twice pays once', function (): void {
    $luce = bankSortPlanned('Luce');
    $transaction = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->laura);
    $this->post(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $luce->id])->assertRedirect();
    $this->post(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $luce->id])->assertRedirect(route('budget.bank.index'));

    expect(insideBianchi(fn (): int => Expense::query()->where('planned_expense_id', $luce->id)->count()))->toBe(1);
});

test('a transaction that was declined can be assigned by hand', function (): void {
    $luce = bankSortPlanned('Luce');
    $transaction = bankSortOne(['amount' => '-72.40', 'declined_at' => now()]);

    $this->actingAs($this->laura)->post(route('budget.bank.assign', $transaction), ['kind' => 'expense', 'planned_id' => $luce->id])->assertRedirect();

    expect($transaction->refresh()->expense_id)->not->toBeNull();
});

test('the planned items of the sheet are those of the kind with a due date to pay, with the closest date', function (): void {
    bankSortPlanned('Luce', '2026-01-05', ['amount' => 72, 'is_estimated' => true]);
    bankSortPlanned('Bollo', '2027-06-01');
    bankSortPlanned('Vecchio', '2025-01-05', ['end_date' => '2026-06-01']);
    $transaction = bankSortOne(['amount' => '-72.40']);

    $this->actingAs($this->marco)->get(route('budget.bank.index', ['assign' => $transaction->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->missing('planned_options')
        ->reloadOnly('planned_options', fn (AssertableInertia $reload): AssertableInertia => $reload
            ->has('planned_options', 1)
            ->where('planned_options.0.title', 'Luce')
            ->where('planned_options.0.kind', 'expense')
            ->where('planned_options.0.due_on', '2026-11-05')
            ->where('planned_options.0.late', false)
            ->where('planned_options.0.estimated', true)
            ->where('planned_options.0.amount', 72)));

    $this->get(route('budget.bank.index', ['assign' => 99999999]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->reloadOnly('planned_options', fn (AssertableInertia $reload): AssertableInertia => $reload->where('planned_options', [])));
});

test('the planned form starts from the text of the transaction when the reading is off', function (): void {
    $transaction = bankSortOne(['text' => 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789']);
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());

    $this->actingAs($this->marco)->get(route('budget.planned.create', ['kind' => 'expense', 'rule_from' => $transaction->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('values.bank_match', BankMatch::fromText($transaction->text)));

    app()->instance(DocumentReader::class, new FakeDocumentReader);
    $this->get(route('budget.planned.create', ['kind' => 'expense', 'rule_from' => $transaction->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('values.bank_match', null));
});

test('a new movement from the bank finds and locks its planned item before reading anything of it', function (): void {
    $luce = bankSortPlanned('Luce');
    $transaction = bankSortOne(['amount' => '-72.40']);

    DB::enableQueryLog();
    $this->actingAs($this->marco)->post(route('budget.movements.store'), bankSortBody($transaction, ['planned_id' => $luce->id, 'occurrence_date' => '2026-11-05']))->assertRedirect(route('budget.bank.index'));
    $queries = array_column(DB::getQueryLog(), 'query');
    // The save transaction starts with the lock of the bank row; the validation of the request ran before it
    $start = array_find_key($queries, static fn (string $sql): bool => str_contains($sql, 'from `bank_transactions`') && str_contains($sql, 'for update'));
    $inside = array_slice($queries, (int) $start);
    $firstPlanned = array_find_key($inside, static fn (string $sql): bool => str_contains($sql, 'from `planned_expenses`'));

    // From the lock of the bank row on, the first read of the planned item is the locking one, and no read of the
    // payments comes before it: the snapshot of the transaction starts after the lock
    expect($start)->not->toBeNull()
        ->and($inside[$firstPlanned])->toContain('for update')
        ->and(array_filter(array_slice($inside, 0, $firstPlanned), static fn (string $sql): bool => str_contains($sql, 'from `expenses`')))->toBe([]);
});

test('a due date paid while the movement from the bank starts saving gives the error, no second expense and the transaction still to sort', function (): void {
    $luce = bankSortPlanned('Luce');
    $transaction = bankSortOne(['amount' => '-72.40']);
    $paid = (object) ['done' => false];

    // Somebody pays the due date right before the save transaction starts, after the request was validated
    DB::connection()->beforeStartingTransaction(function (Connection $connection) use ($luce, $paid): void {
        if ($paid->done || $connection->transactionLevel() < 1) {
            return;
        }

        $paid->done = true;
        Expense::factory()->create(['family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-05', 'amount' => 72.0, 'date' => '2026-11-05']);
    });

    $this->actingAs($this->marco)->post(route('budget.movements.store'), bankSortBody($transaction, ['planned_id' => $luce->id, 'occurrence_date' => '2026-11-05']))->assertSessionHasErrors('occurrence_date');

    expect($paid->done)->toBeTrue()
        ->and(insideBianchi(fn (): int => Expense::query()->where('planned_expense_id', $luce->id)->count()))->toBe(1)
        ->and($transaction->refresh()->expense_id)->toBeNull();
});
