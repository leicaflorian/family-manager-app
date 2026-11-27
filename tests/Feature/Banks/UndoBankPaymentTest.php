<?php

declare(strict_types=1);

use App\Actions\Banks\LinkBankMovement;
use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SettleFromBank;
use App\Actions\Budget\MovePlannedDocuments;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\BankMatcher;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->laura = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member]);
    FinancialAccount::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Conto BancoPosta', 'last_four_digits' => '3307']);
});

/**
 * The Edison debit of 72,40 € settled by the rule of Luce: returns the planned expense, the transaction and the expense.
 *
 * @param  BankMatchSource  $by  how it is linked
 * @return array{PlannedExpense, BankTransaction, Expense}
 */
function bankUndoSettled(BankMatchSource $by = BankMatchSource::Rule): array
{
    return insideBianchi(function () use ($by): array {
        $family = Family::query()->where('name', 'Bianchi')->sole();
        $luce = PlannedExpense::factory()->create([
            'family_id' => $family->id, 'title' => 'Luce', 'bank_match' => 'SDD.*EDISON', 'amount' => 72.0,
            'fee_amount' => null, 'is_estimated' => false, 'start_date' => '2026-01-05', 'financial_account_id' => null,
        ]);
        $transaction = BankTransaction::factory()->create([
            'family_id' => $family->id,
            'financial_account_id' => FinancialAccount::query()->where('name', 'Conto BancoPosta')->sole()->id,
            'text' => 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA', 'amount' => '-72.40', 'booked_on' => '2026-11-05', 'value_on' => '2026-11-05',
        ]);
        $expense = resolve(SettleFromBank::class)->handle($transaction, resolve(BankMatcher::class)->planned($transaction) ?? $luce, $by);
        expect($expense)->toBeInstanceOf(Expense::class);

        return [$luce, $transaction->refresh(), $expense];
    });
}

test('undoing removes the expense, reopens the due date and puts the transaction back, declined', function (): void {
    [$luce, $transaction, $expense] = bankUndoSettled();
    $document = insideBianchi(function () use ($expense): Document {
        $document = Document::factory()->create(['family_id' => $expense->family_id]);
        $expense->documents()->attach($document);

        return $document;
    });

    $this->actingAs($this->marco)
        ->delete("/budget/bank/{$transaction->id}/payment")
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('toast.message', 'The due date is to be paid again.');

    insideBianchi(function () use ($luce, $transaction, $expense, $document): void {
        $transaction->refresh();
        expect(Expense::query()->find($expense->id))->toBeNull()
            ->and(Document::query()->find($document->id))->not->toBeNull()
            ->and($transaction->expense_id)->toBeNull()
            ->and($transaction->income_id)->toBeNull()
            ->and($transaction->matched_by)->toBeNull()
            ->and($transaction->declined_at)->not->toBeNull()
            ->and(resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-11-05'))->isPaid())->toBeFalse()
            ->and(ReminderNotice::query()->where('bank_transaction_id', $transaction->id)->exists())->toBeTrue();
    });

    // Back in *From the bank*, and the rule does not take it again
    $this->get('/budget/bank')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('rows.0.id', $transaction->id));
    insideBianchi(function () use ($transaction): void {
        expect(resolve(MatchBankTransactions::class)->handle([$transaction->refresh()]))->toBe(0)
            ->and(Expense::query()->where('title', 'like', 'Luce%')->count())->toBe(0);
    });
});

test('a member cannot undo', function (): void {
    [, $transaction, $expense] = bankUndoSettled();

    $this->actingAs($this->laura)->delete("/budget/bank/{$transaction->id}/payment")->assertForbidden();

    insideBianchi(function () use ($transaction, $expense): void {
        expect(Expense::query()->find($expense->id))->not->toBeNull()
            ->and($transaction->refresh()->expense_id)->toBe($expense->id)
            ->and($transaction->declined_at)->toBeNull();
    });
});

test('a movement linked as a duplicate or created by hand has no undo', function (BankMatchSource $by): void {
    [, $transaction, $expense] = bankUndoSettled();
    insideBianchi(fn () => $transaction->forceFill(['matched_by' => $by])->save());

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertUnprocessable();
    $this->get("/budget/movements/expense/{$expense->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.bank.undo_url', null)->where('movement.bank.text', 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA'));
    insideBianchi(fn () => expect(Expense::query()->find($expense->id))->not->toBeNull());
})->with([BankMatchSource::Duplicate, BankMatchSource::Created]);

test('a transaction that is not linked cannot be undone', function (): void {
    [, $transaction] = bankUndoSettled();
    insideBianchi(fn () => $transaction->forceFill(['expense_id' => null, 'matched_by' => null])->save());

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertUnprocessable();
});

test('the transaction of another family answers 404', function (): void {
    [, $transaction] = bankUndoSettled();
    $other = registerFamily('Rossi', 'rossi@example.com');
    $other->family->forceFill(['bank_feed_enabled' => true])->save();

    $this->actingAs($other)->delete("/budget/bank/{$transaction->id}/payment")->assertNotFound();
});

test('the routes answer 404 while the feed is off', function (): void {
    [, $transaction] = bankUndoSettled();
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertNotFound();
});

test('the movement page says it came from the bank', function (): void {
    [, $transaction, $expense] = bankUndoSettled();

    $this->actingAs($this->marco)->get("/budget/movements/expense/{$expense->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('movement.bank.booked_on', '2026-11-05')
            ->where('movement.bank.text', 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA')
            ->where('movement.bank.account', 'Conto BancoPosta ·· 3307')
            ->where('movement.bank.undo_url', "/budget/bank/{$transaction->id}/payment"));

    // A member sees the line but has no undo
    $this->actingAs($this->laura)->get("/budget/movements/expense/{$expense->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.bank.undo_url', null)->has('movement.bank.text'));
});

test('a movement without the bank has none', function (): void {
    $expense = insideBianchi(fn (): Expense => Expense::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]));

    $this->actingAs($this->marco)->get("/budget/movements/expense/{$expense->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.bank', null));
});

test('undoing locks the bank row and then the planned item before any plain read', function (): void {
    [, $transaction] = bankUndoSettled();

    DB::enableQueryLog();
    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));
    $queries = array_column(DB::getQueryLog(), 'query');
    $start = array_find_key($queries, static fn (string $sql): bool => str_contains($sql, 'from `bank_transactions`') && str_contains($sql, 'for update'));
    $inside = array_slice($queries, (int) $start);
    $plannedLock = array_find_key($inside, static fn (string $sql): bool => str_contains($sql, 'from `planned_expenses`') && str_contains($sql, 'for update'));

    expect($start)->not->toBeNull()
        ->and($plannedLock)->not->toBeNull()
        ->and(array_values(array_filter(
            array_slice($inside, 1, max(0, (int) $plannedLock - 1)),
            static fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'select') && ! str_contains($sql, 'for update'),
        )))->toBe([]);
});

/**
 * What the Budget calls the due dates of Luce and Gas of the dual-fuel bill.
 *
 * @param  array{luce: PlannedExpense, gas: PlannedExpense, account: FinancialAccount, document: Document}  $bill
 * @return list<string>
 */
function bankUndoDueTitles(array $bill): array
{
    return [
        resolve(Deadlines::class)->find($bill['luce'], CarbonImmutable::parse('2026-11-23'))->title,
        resolve(Deadlines::class)->find($bill['gas'], CarbonImmutable::parse('2026-11-30'))->title,
    ];
}

test('undoing the debit of the dual-fuel bill takes back both payments and reopens both due dates', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
    [$bill, $transaction, $titles] = insideBianchi(function (): array {
        $bill = enelDualFuelBill();
        $titles = bankUndoDueTitles($bill);
        $transaction = enelDualFuelDebit($bill['account']);
        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(1)
            ->and(Expense::query()->count())->toBe(2);

        return [$bill, $transaction->refresh(), $titles];
    });

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($bill, $transaction, $titles): void {
        $transaction->refresh();
        expect(Expense::query()->count())->toBe(0)
            ->and(Document::query()->find($bill['document']->id))->not->toBeNull()
            ->and($transaction->expense_id)->toBeNull()
            ->and($transaction->matched_by)->toBeNull()
            ->and($transaction->declined_at)->not->toBeNull()
            ->and($transaction->splitExpenses()->count())->toBe(0)
            ->and(resolve(Deadlines::class)->find($bill['luce'], CarbonImmutable::parse('2026-11-23'))->isPaid())->toBeFalse()
            ->and(resolve(Deadlines::class)->find($bill['gas'], CarbonImmutable::parse('2026-11-30'))->isPaid())->toBeFalse()
            ->and(Document::query()->waitingFor([$bill['luce']->id])->pluck('id')->all())->toBe([$bill['document']->id])
            ->and(Document::query()->waitingFor([$bill['gas']->id])->pluck('id')->all())->toBe([$bill['document']->id])
            ->and(resolve(MovePlannedDocuments::class)->waiting($bill['luce'], CarbonImmutable::parse('2026-11-23'))->pluck('documents.id')->all())->toBe([$bill['document']->id])
            ->and(resolve(MovePlannedDocuments::class)->waiting($bill['gas'], CarbonImmutable::parse('2026-11-30'))->pluck('documents.id')->all())->toBe([$bill['document']->id])
            ->and(bankUndoDueTitles($bill))->toBe($titles);
    });
});

test('undoing the dual-fuel debit locks the bank row, then both planned expenses in order of id, before any plain read', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
    [$bill, $transaction] = insideBianchi(function (): array {
        $bill = enelDualFuelBill();
        $transaction = enelDualFuelDebit($bill['account']);
        resolve(MatchBankTransactions::class)->handle([$transaction]);

        return [$bill, $transaction];
    });

    DB::enableQueryLog();
    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));
    $log = DB::getQueryLog();
    $start = array_find_key($log, static fn (array $query): bool => str_contains($query['query'], 'from `bank_transactions`') && str_contains($query['query'], 'for update'));
    $inside = array_slice($log, (int) $start + 1);
    $locks = array_values(array_filter($inside, static fn (array $query): bool => str_contains($query['query'], 'from `planned_expenses`') && str_contains($query['query'], 'for update')));
    $secondLock = array_find_key($inside, static fn (array $query): bool => $query === ($locks[1] ?? null));

    expect($start)->not->toBeNull()
        ->and(array_map(static fn (array $query): mixed => $query['bindings'][0] ?? null, array_slice($locks, 0, 2)))->toBe([$bill['luce']->id, $bill['gas']->id])
        ->and(array_values(array_filter(
            array_slice($inside, 0, (int) $secondLock),
            static fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select') && ! str_contains($query['query'], 'for update'),
        )))->toBe([]);
});

/**
 * The dual-fuel debit of 27/11/2026 paid by the rule: the bill, the transaction and the payments of Luce and Gas.
 *
 * @return array{array{luce: PlannedExpense, gas: PlannedExpense, account: FinancialAccount, document: Document}, BankTransaction, Expense, Expense}
 */
function bankUndoDualFuelPaid(): array
{
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));

    return insideBianchi(function (): array {
        $bill = enelDualFuelBill();
        $transaction = enelDualFuelDebit($bill['account']);
        resolve(MatchBankTransactions::class)->handle([$transaction]);
        $payments = Expense::query()->orderBy('planned_expense_id')->get();

        return [$bill, $transaction->refresh(), $payments[0], $payments[1]];
    });
}

test('deleting the Luce payment of the dual-fuel debit frees Gas and the debit, and the next match pays only once', function (): void {
    [$bill, $transaction, $luce, $gas] = bankUndoDualFuelPaid();

    $this->actingAs($this->marco)->delete("/budget/movements/expense/{$luce->id}")->assertRedirect();

    insideBianchi(function () use ($bill, $transaction, $gas): void {
        expect($gas->refresh()->bank_transaction_id)->toBeNull()
            ->and($transaction->refresh()->expense_id)->toBeNull();

        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(1);

        $new = Expense::query()->whereKeyNot($gas->id)->sole();
        expect($new->planned_expense_id)->toBe($bill['luce']->id)
            ->and((float) $new->amount)->toBe(165.65)
            ->and($new->bank_transaction_id)->toBeNull()
            ->and($transaction->refresh()->expense_id)->toBe($new->id)
            ->and($transaction->splitExpenses()->count())->toBe(0);
    });
});

test('after deleting the Luce payment, undoing a debit assigned by hand takes back only that payment', function (): void {
    [$bill, $transaction, $luce, $gas] = bankUndoDualFuelPaid();
    $this->actingAs($this->marco)->delete("/budget/movements/expense/{$luce->id}")->assertRedirect();
    $assigned = insideBianchi(fn (): ?Expense => resolve(SettleFromBank::class)->handle($transaction->refresh(), $bill['luce'], BankMatchSource::Assigned));
    expect($assigned)->toBeInstanceOf(Expense::class);

    $this->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($assigned, $gas): void {
        expect(Expense::query()->find($assigned->id))->toBeNull()
            ->and(Expense::query()->find($gas->id))->not->toBeNull();
    });
});

test('a split left behind is ignored by the undo and cleared by a new link', function (): void {
    [, $transaction, $luce, $gas] = bankUndoDualFuelPaid();
    $other = insideBianchi(function () use ($transaction, $luce): Expense {
        // As if the debit lost its first payment without the siblings being told
        $transaction->forceFill(['expense_id' => null, 'matched_by' => null])->save();
        $luce->forceFill(['bank_transaction_id' => null])->save();
        $other = Expense::factory()->create(['family_id' => $transaction->family_id, 'amount' => 165.65, 'date' => '2026-11-21']);
        resolve(LinkBankMovement::class)->handle($transaction, $other, BankMatchSource::Created);

        return $other;
    });

    insideBianchi(function () use ($transaction, $gas, $other): void {
        expect($gas->refresh()->bank_transaction_id)->toBeNull()
            ->and($transaction->refresh()->expense_id)->toBe($other->id);
    });
});

test('a split whose first payment is not among its expenses is not trusted by the undo', function (): void {
    [, $transaction, $luce, $gas] = bankUndoDualFuelPaid();
    $assigned = insideBianchi(function () use ($transaction, $luce): Expense {
        // A stale sibling left on the debit, now linked to another payment by the person
        $luce->forceFill(['bank_transaction_id' => null])->save();
        $assigned = Expense::factory()->create(['family_id' => $transaction->family_id, 'planned_expense_id' => $luce->planned_expense_id, 'occurrence_date' => '2026-12-23', 'amount' => 165.65, 'date' => '2026-11-21']);
        $transaction->forceFill(['expense_id' => $assigned->id, 'matched_by' => BankMatchSource::Assigned])->save();

        return $assigned;
    });

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($assigned, $gas, $luce): void {
        expect(Expense::query()->find($assigned->id))->toBeNull()
            ->and(Expense::query()->find($gas->id))->not->toBeNull()
            ->and(Expense::query()->find($luce->id))->not->toBeNull();
    });
});

test('deleting the Gas payment of the dual-fuel debit leaves Luce linked', function (): void {
    [, $transaction, $luce, $gas] = bankUndoDualFuelPaid();

    $this->actingAs($this->marco)->delete("/budget/movements/expense/{$gas->id}")->assertRedirect();

    insideBianchi(function () use ($transaction, $luce): void {
        expect($transaction->refresh()->expense_id)->toBe($luce->id)
            ->and($luce->refresh()->bank_transaction_id)->toBe($transaction->id);
    });
});
