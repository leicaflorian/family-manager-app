<?php

declare(strict_types=1);

use App\Actions\Banks\LinkBankMovement;
use App\Actions\Banks\LinkRecordedMovement;
use App\Actions\Banks\MatchBankTransactions;
use App\Actions\Banks\SettleFromBank;
use App\Actions\Banks\UndoBankPayment;
use App\Actions\Budget\MovePlannedDocuments;
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

/**
 * What the Budget calls the due dates of Luce and Gas of the dual-fuel bill.
 *
 * @param  array{luce: PlannedExpense, gas: PlannedExpense, account: FinancialAccount, document: Document, filters: array{luce: BankFilter, gas: BankFilter}}  $bill
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
 * @return array{array{luce: PlannedExpense, gas: PlannedExpense, account: FinancialAccount, document: Document, filters: array{luce: BankFilter, gas: BankFilter}}, BankTransaction, Expense, Expense}
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

test('the dual-fuel debit linked to the two payments already recorded is only unlinked: both stay, and the pairs are not proposed again', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
    [$transaction, $paid] = insideBianchi(function (): array {
        $bill = enelDualFuelBill();
        $paid = array_map(static function (array $row) use ($bill): Expense {
            $expense = Expense::factory()->create([
                'family_id' => $bill['account']->family_id, 'planned_expense_id' => $row[0]->id, 'occurrence_date' => $row[1],
                'date' => '2026-11-20', 'amount' => $row[2], 'financial_account_id' => $bill['account']->id,
            ]);
            $expense->documents()->attach($bill['document']);

            return $expense;
        }, [[$bill['luce'], '2026-11-23', 96.25], [$bill['gas'], '2026-11-30', 69.40]]);
        $transaction = enelDualFuelDebit($bill['account']);
        expect(resolve(MatchBankTransactions::class)->handle([$transaction]))->toBe(1)
            ->and($transaction->refresh()->matched_by)->toBe(BankMatchSource::Duplicate);

        return [$transaction, $paid];
    });

    $this->actingAs($this->laura)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($transaction, $paid): void {
        $transaction->refresh();
        expect(Expense::query()->orderBy('id')->pluck('id')->all())->toBe([$paid[0]->id, $paid[1]->id])
            ->and(Expense::query()->whereNotNull('bank_transaction_id')->count())->toBe(0)
            ->and($transaction->expense_id)->toBeNull()
            ->and($transaction->bank_filter_id)->toBeNull()
            ->and($transaction->declined_at)->not->toBeNull()
            ->and($transaction->rejected_proposals)->toBe(['expense:'.$paid[0]->id, 'expense:'.$paid[1]->id]);
    });
});

test('undoing the dual-fuel debit with its commission puts the commission back to sort and takes back both payments', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-27 07:00', 'Europe/Rome'));
    [$debit, $fee] = insideBianchi(function (): array {
        $bill = enelDualFuelBill();
        $mandate = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) ENEL ENERGIA S.P.A. CID.IT12ZZZ0000000000000000001 MAN.ENEL0042';
        $debit = enelDualFuelDebit($bill['account']);
        $debit->forceFill(['text' => $mandate])->save();
        $fee = enelDualFuelDebit($bill['account'], '-0.40');
        $fee->forceFill(['text' => 'COMMISSIONI '.$mandate, 'booked_on' => '2026-11-22', 'value_on' => '2026-11-22'])->save();
        expect(resolve(MatchBankTransactions::class)->handle([$debit->refresh()]))->toBe(1)
            ->and($fee->refresh()->matched_by)->toBe(BankMatchSource::Fee);

        return [$debit->refresh(), $fee];
    });

    $this->actingAs($this->marco)->delete("/budget/bank/{$debit->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($debit, $fee): void {
        $fee->refresh();
        expect(Expense::query()->count())->toBe(0)
            ->and($fee->expense_id)->toBeNull()
            ->and($fee->matched_by)->toBeNull()
            ->and($fee->declined_at)->toBeNull()
            ->and($debit->refresh()->declined_at)->not->toBeNull();
    });
});
