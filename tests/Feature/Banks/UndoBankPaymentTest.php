<?php

declare(strict_types=1);

use App\Actions\Banks\LinkRecordedMovement;
use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SettleFromBank;
use App\Actions\Banks\UndoBankPayment;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\BankFees;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $expense = resolve(SettleFromBank::class)->handle($transaction, $luce, $by);
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

test('a movement created by hand or the commission of a debit has no undo', function (BankMatchSource $by): void {
    [, $transaction, $expense] = bankUndoSettled();
    insideBianchi(fn () => $transaction->forceFill(['matched_by' => $by])->save());

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertUnprocessable();

    // The commission row of a debit is not the movement's own line, so only the created one shows a box
    if ($by === BankMatchSource::Created) {
        $this->get("/budget/movements/expense/{$expense->id}")
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.bank.undo_url', null)->where('movement.bank.undo', null)->where('movement.bank.text', 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA'));
    }

    insideBianchi(fn () => expect(Expense::query()->find($expense->id))->not->toBeNull());
})->with([BankMatchSource::Created, BankMatchSource::Fee]);

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

test('undoing the debit puts the commission back to sort under it', function (): void {
    $mandate = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
    [$debit, $fee, $expense] = insideBianchi(function () use ($mandate): array {
        $family = Family::query()->where('name', 'Bianchi')->sole();
        $account = FinancialAccount::query()->where('name', 'Conto BancoPosta')->sole();
        $satispay = PlannedExpense::factory()->create([
            'family_id' => $family->id, 'title' => 'Ricarica Satispay', 'bank_match' => 'MAN.SAT0023101207360112900', 'amount' => 10.0,
            'fee_amount' => null, 'is_estimated' => false, 'start_date' => '2026-01-04', 'financial_account_id' => null,
        ]);
        $row = static fn (string $text, string $amount, string $day): BankTransaction => BankTransaction::factory()->create([
            'family_id' => $family->id, 'financial_account_id' => $account->id, 'text' => $text, 'amount' => $amount, 'booked_on' => $day, 'value_on' => $day,
        ]);
        $debit = $row($mandate, '-10.00', '2026-11-04');
        $fee = $row('COMMISSIONI '.$mandate, '-0.40', '2026-11-05');
        $expense = resolve(SettleFromBank::class)->handle($debit, $satispay, BankMatchSource::Rule);
        expect((float) $expense?->amount)->toBe(10.4)
            ->and($fee->refresh()->matched_by)->toBe(BankMatchSource::Fee);

        return [$debit->refresh(), $fee, $expense];
    });

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($debit, $fee, $expense): void {
        $fee->refresh();
        expect(Expense::query()->find($expense?->id))->toBeNull()
            ->and($fee->expense_id)->toBeNull()
            ->and($fee->matched_by)->toBeNull()
            ->and($fee->declined_at)->toBeNull()
            ->and(resolve(BankFees::class)->debitOf($fee)?->is($debit))->toBeTrue();
    });
});

/**
 * A hand-recorded expense of the given amount linked as a duplicate to a debit of 10,00 € (with the commission of 0,40 €
 * of its direct debit read before it, when asked): returns the debit, the commission (or null) and the expense.
 *
 * @param  float  $amount  what the expense says
 * @param  bool  $withFee  whether the bank gave the commission too
 * @param  float|null  $ownFee  the fee the hand-recorded expense already had
 * @return array{BankTransaction, BankTransaction|null, Expense}
 */
function bankUndoDuplicate(float $amount = 10.0, bool $withFee = false, ?float $ownFee = null): array
{
    return insideBianchi(function () use ($amount, $withFee, $ownFee): array {
        $family = Family::query()->where('name', 'Bianchi')->sole();
        $account = FinancialAccount::query()->where('name', 'Conto BancoPosta')->sole();
        $mandate = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
        $row = static fn (string $text, string $value, string $day): BankTransaction => BankTransaction::factory()->create([
            'family_id' => $family->id, 'financial_account_id' => $account->id, 'text' => $text, 'amount' => $value, 'booked_on' => $day, 'value_on' => $day,
        ]);
        $fee = $withFee ? $row('COMMISSIONI '.$mandate, '-0.40', '2026-11-05') : null;
        $debit = $row($mandate, '-10.00', '2026-11-04');
        $expense = Expense::factory()->create(['family_id' => $family->id, 'title' => 'Ricarica Satispay', 'amount' => $amount, 'fee_amount' => $ownFee, 'date' => '2026-11-04']);
        resolve(LinkRecordedMovement::class)->handle($debit, $expense);

        return [$debit->refresh(), $fee?->refresh(), $expense->refresh()];
    });
}

test('a payment made by a filter is deleted and the due date is to pay again', function (): void {
    [$luce, $transaction, $expense] = bankUndoSettled();
    insideBianchi(function () use ($transaction): void {
        $filter = BankFilter::factory()->create(['family_id' => $transaction->family_id, 'name' => 'Luce']);
        $transaction->forceFill(['bank_filter_id' => $filter->id])->save();
    });

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($luce, $transaction, $expense): void {
        $transaction->refresh();
        expect(Expense::query()->find($expense->id))->toBeNull()
            ->and($transaction->matched_by)->toBeNull()
            ->and($transaction->bank_filter_id)->toBeNull()
            ->and($transaction->declined_at)->not->toBeNull()
            ->and(resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-11-05'))->isPaid())->toBeFalse();
    });
});

test('an expense that was already there is only unlinked and stays', function (): void {
    [$debit, , $expense] = bankUndoDuplicate();
    insideBianchi(function () use ($debit): void {
        $filter = BankFilter::factory()->create(['family_id' => $debit->family_id]);
        $debit->forceFill(['bank_filter_id' => $filter->id])->save();
    });

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($debit, $expense): void {
        $debit->refresh();
        expect(Expense::query()->find($expense->id))->not->toBeNull()
            ->and($debit->expense_id)->toBeNull()
            ->and($debit->income_id)->toBeNull()
            ->and($debit->matched_by)->toBeNull()
            ->and($debit->bank_filter_id)->toBeNull()
            ->and($debit->declined_at)->not->toBeNull();
    });
});

test('a member can unlink it and cannot delete the one a filter made', function (): void {
    [$debit, , $expense] = bankUndoDuplicate();
    [, $made, $createdByFilter] = bankUndoSettled();
    insideBianchi(function () use ($made): void {
        $made->forceFill(['bank_filter_id' => BankFilter::factory()->create(['family_id' => $made->family_id])->id])->save();
    });

    $this->actingAs($this->laura)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect(route('budget.bank.index'));
    $this->actingAs($this->laura)->delete("/budget/bank/{$made->id}/payment")->assertForbidden();

    insideBianchi(function () use ($debit, $expense, $made, $createdByFilter): void {
        expect(Expense::query()->find($expense->id))->not->toBeNull()
            ->and($debit->refresh()->expense_id)->toBeNull()
            ->and(Expense::query()->find($createdByFilter->id))->not->toBeNull()
            ->and($made->refresh()->expense_id)->toBe($createdByFilter->id);
    });
});

test('the pair is not proposed again', function (): void {
    [$debit, , $expense] = bankUndoDuplicate();

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect();

    insideBianchi(function () use ($debit, $expense): void {
        $debit->refresh();
        expect($debit->rejected_proposals)->toContain('expense:'.$expense->id);
        // Neither a rule nor a list of duplicates takes the pair back by itself
        expect(resolve(MatchBankTransactions::class)->handle([$debit]))->toBe(0)
            ->and($debit->refresh()->expense_id)->toBeNull();
    });
});

test('the fee is taken off the expense that stays', function (): void {
    [$debit, $fee, $expense] = bankUndoDuplicate(10.0, true);
    expect((float) $expense->amount)->toBe(10.4)
        ->and((float) $expense->fee_amount)->toBe(0.4)
        ->and($fee?->matched_by)->toBe(BankMatchSource::Fee);

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect();

    insideBianchi(function () use ($expense, $fee): void {
        $expense->refresh();
        $fee?->refresh();
        expect((float) $expense->amount)->toBe(10.0)
            ->and($expense->fee_amount)->toBeNull()
            ->and($fee?->expense_id)->toBeNull()
            ->and($fee?->matched_by)->toBeNull()
            ->and($fee?->declined_at)->toBeNull();
    });
});

test('a hand-recorded 10,40 linked as duplicate keeps 10,40 when unlinked', function (): void {
    [$debit, $fee, $expense] = bankUndoDuplicate(10.4, true);
    expect((float) $expense->amount)->toBe(10.4)
        ->and((float) $expense->fee_amount)->toBe(0.4);

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect();

    insideBianchi(function () use ($expense, $fee): void {
        $expense->refresh();
        expect((float) $expense->amount)->toBe(10.4)
            ->and($expense->fee_amount)->toBeNull()
            ->and($fee?->refresh()->matched_by)->toBeNull();
    });
});

test('a hand-recorded expense with a fee of its own keeps it when the included commission goes', function (): void {
    [$debit, , $expense] = bankUndoDuplicate(10.4, true, 0.5);
    expect((float) $expense->fee_amount)->toBe(0.5);

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect();

    insideBianchi(function () use ($expense): void {
        expect((float) $expense->refresh()->amount)->toBe(10.4)->and((float) $expense->fee_amount)->toBe(0.5);
    });
});

test('a transfer made by a filter is put back and no filter takes it again', function (): void {
    $transfer = insideBianchi(function (): BankTransaction {
        $family = Family::query()->where('name', 'Bianchi')->sole();
        $filter = BankFilter::factory()->create(['family_id' => $family->id, 'name' => 'Giro']);
        $other = FinancialAccount::factory()->create(['family_id' => $family->id, 'name' => 'Buddy Bank']);

        return BankTransaction::factory()->create([
            'family_id' => $family->id,
            'financial_account_id' => FinancialAccount::query()->where('name', 'Conto BancoPosta')->sole()->id,
            'amount' => '-200.00', 'transfer_account_id' => $other->id, 'bank_filter_id' => $filter->id,
        ]);
    });

    $this->actingAs($this->marco)->delete(route('budget.bank.restore', $transfer))->assertRedirect();

    insideBianchi(function () use ($transfer): void {
        $transfer->refresh();
        expect($transfer->transfer_account_id)->toBeNull()
            ->and($transfer->bank_filter_id)->toBeNull()
            ->and($transfer->declined_at)->not->toBeNull();
    });
});

test('the box of the movement says how it was linked, its commission and its filter', function (): void {
    [$debit, $fee, $expense] = bankUndoDuplicate(10.0, true);
    $filter = insideBianchi(function () use ($debit): BankFilter {
        $filter = BankFilter::factory()->create(['family_id' => $debit->family_id, 'name' => 'Satispay']);
        $debit->forceFill(['bank_filter_id' => $filter->id])->save();

        return $filter;
    });

    $this->actingAs($this->marco)->get("/budget/movements/expense/{$expense->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('movement.bank.how', 'duplicate')
            ->where('movement.bank.fee.id', $fee?->id)
            ->where('movement.bank.fee.amount', -0.4)
            ->where('movement.bank.fee.booked_on', '2026-11-05')
            ->where('movement.bank.filter', ['id' => $filter->id, 'name' => 'Satispay', 'url' => route('budget.bank.filters.edit', ['filter' => $filter->id], absolute: false)])
            ->where('movement.bank.undo', 'detach')
            ->where('movement.bank.undo_url', "/budget/bank/{$debit->id}/payment")
            ->where('movement.bank.url', '/budget/bank?show=all&q='.rawurlencode(substr($debit->text, 0, 100))));

    // The same box for a member of a payment a filter made: the commission and the filter, no undo
    [, $made, $payment] = bankUndoSettled();
    $this->actingAs($this->laura)->get("/budget/movements/expense/{$payment->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.bank.how', 'rule')->where('movement.bank.fee', null)->where('movement.bank.filter', null)->where('movement.bank.undo', null));
});

test('undoing a duplicate locks the bank row first and the commission rows after', function (): void {
    [$debit] = bankUndoDuplicate(10.0, true);

    DB::enableQueryLog();
    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect();
    $queries = array_column(DB::getQueryLog(), 'query');
    $start = array_find_key($queries, static fn (string $sql): bool => str_contains($sql, 'from `bank_transactions`') && str_contains($sql, 'for update'));
    $inside = array_slice($queries, (int) $start);

    expect($start)->not->toBeNull()
        ->and(array_values(array_filter(
            array_slice($inside, 1, 1),
            static fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'select') && ! str_contains($sql, 'for update'),
        )))->toBe([]);
});

test('a fee edited by hand after the commission joined survives the unlinking', function (): void {
    [$debit, , $expense] = bankUndoDuplicate(10.4, true);
    insideBianchi(fn () => $expense->forceFill(['fee_amount' => 0.9])->save());

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect();

    insideBianchi(function () use ($expense): void {
        expect((float) $expense->refresh()->amount)->toBe(10.4)->and((float) $expense->fee_amount)->toBe(0.5);
    });
});

test('what was authorised must still be what the row is under the lock', function (): void {
    [, $payment] = bankUndoSettled();
    [$duplicate] = bankUndoDuplicate();

    insideBianchi(function () use ($payment, $duplicate): void {
        // The person was allowed to delete a payment, but the row is a link to an expense already there now
        expect(fn () => resolve(UndoBankPayment::class)->handle($duplicate, detachOnly: false))->toThrow(HttpException::class)
            // And the other way round: allowed only to unlink, but the row is a payment of the bank
            ->and(fn () => resolve(UndoBankPayment::class)->handle($payment, detachOnly: true))->toThrow(HttpException::class)
            ->and(Expense::query()->count())->toBe(2);
    });
});
