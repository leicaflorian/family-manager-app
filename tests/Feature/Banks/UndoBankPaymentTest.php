<?php

declare(strict_types=1);

use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SettleFromBank;
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
