<?php

declare(strict_types=1);

use App\Actions\Banks\ApplyBankFilter;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Enums\BankFilterOutcome;
use App\Enums\BankMatchSource;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-26 07:00', 'Europe/Rome'));
    registerFamily();
});

/** The id of the Bianchi family. */
function applyBankFilterFamily(): int
{
    return Family::query()->where('name', 'Bianchi')->sole()->id;
}

/** The BancoPosta account of the Bianchi, made once. */
function applyBankFilterAccount(): FinancialAccount
{
    return FinancialAccount::query()->where('name', 'Conto BancoPosta')->first()
        ?? FinancialAccount::factory()->create(['family_id' => applyBankFilterFamily(), 'name' => 'Conto BancoPosta']);
}

/**
 * A booked transaction of the Bianchi on BancoPosta.
 *
 * @param  array<string, mixed>  $state  more columns
 */
function applyBankFilterRow(string $text, string $amount, string $day, array $state = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => applyBankFilterFamily(),
        'financial_account_id' => applyBankFilterAccount()->id,
        'text' => $text,
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$state,
    ]);
}

/**
 * A filter of the Bianchi that creates the expense «Ricarica Satispay» for the word SATISPAY.
 *
 * @param  array<string, mixed>  $state  more columns
 */
function applyBankFilterSatispay(array $state = []): BankFilter
{
    return BankFilter::factory()->create([
        'family_id' => applyBankFilterFamily(),
        'name' => 'Satispay',
        'contains' => ['SATISPAY'],
        'action' => BankFilterAction::Create,
        'title' => 'Ricarica Satispay',
        ...$state,
    ]);
}

/** The monthly Luce of the Bianchi, 72 € on the 5th from January 2026, and a filter that pays it for EDISON. */
function applyBankFilterLuce(array $state = []): BankFilter
{
    $luce = PlannedExpense::factory()->create([
        'family_id' => applyBankFilterFamily(), 'title' => 'Luce', 'amount' => 72, 'fee_amount' => null,
        'start_date' => '2026-01-05', 'financial_account_id' => null, ...$state,
    ]);

    return BankFilter::factory()->pays($luce)->create(['family_id' => applyBankFilterFamily(), 'name' => 'Luce', 'contains' => ['EDISON']]);
}

test('pays the due date of the planned expense as the rule did', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterLuce();
        $transaction = applyBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::Paid);

        $expense = Expense::query()->where('planned_expense_id', $filter->planned_expense_id)->sole();
        expect($expense->occurrence_date?->toDateString())->toBe('2026-11-05')
            ->and($expense->amount)->toBe('72.40')
            ->and($transaction->refresh()->expense_id)->toBe($expense->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Rule);
    });
});

test('creates the expense with the fields of the filter, the amount, day and account of the transaction', function (): void {
    insideBianchi(function (): void {
        $category = ExpenseCategory::factory()->create(['family_id' => applyBankFilterFamily()]);
        $organization = Organization::factory()->create(['family_id' => applyBankFilterFamily()]);
        $person = Person::factory()->create(['family_id' => applyBankFilterFamily()]);
        $filter = applyBankFilterSatispay([
            'category_id' => $category->id, 'organization_id' => $organization->id, 'person_id' => $person->id, 'notes' => 'Dal telefono',
        ]);
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::Created);

        $expense = Expense::query()->sole();
        expect($expense->title)->toBe('Ricarica Satispay')
            ->and($expense->amount)->toBe('10.00')
            ->and($expense->date?->toDateString())->toBe('2026-11-24')
            ->and($expense->financial_account_id)->toBe(applyBankFilterAccount()->id)
            ->and($expense->category_id)->toBe($category->id)
            ->and($expense->organization_id)->toBe($organization->id)
            ->and($expense->person_id)->toBe($person->id)
            ->and($expense->notes)->toBe('Dal telefono')
            ->and($expense->planned_expense_id)->toBeNull()
            ->and($transaction->refresh()->expense_id)->toBe($expense->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Rule);
    });
});

test('adds the month to the title when asked', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay(['title_with_month' => true]);
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');

        resolve(ApplyBankFilter::class)->handle($transaction, $filter);

        expect(Expense::query()->sole()->title)->toBe('Ricarica Satispay November 2026');
    });
});

test('links the expense already recorded instead of creating one', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay();
        $byHand = Expense::factory()->create(['family_id' => applyBankFilterFamily(), 'title' => 'Satispay', 'amount' => 10, 'date' => '2026-11-21']);
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::Linked)
            ->and(Expense::query()->count())->toBe(1)
            ->and($transaction->refresh()->expense_id)->toBe($byHand->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Duplicate);
    });
});

test('of two transactions of the same amount in a week only one takes the one recorded expense, the other creates', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay();
        $byHand = Expense::factory()->create(['family_id' => applyBankFilterFamily(), 'title' => 'Satispay', 'amount' => 10, 'date' => '2026-11-21']);
        $first = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-20');
        $second = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');
        $apply = resolve(ApplyBankFilter::class);

        expect([$apply->handle($first, $filter), $apply->handle($second, $filter)])->toBe([BankFilterOutcome::Linked, BankFilterOutcome::Created])
            ->and($first->refresh()->expense_id)->toBe($byHand->id)
            ->and($second->refresh()->expense_id)->not->toBe($byHand->id)
            ->and($second->expense_id)->not->toBeNull()
            ->and(Expense::query()->count())->toBe(2);
    });
});

test('marks a transfer', function (): void {
    insideBianchi(function (): void {
        $other = FinancialAccount::factory()->create(['family_id' => applyBankFilterFamily(), 'name' => 'Conto BPER']);
        $filter = applyBankFilterSatispay(['action' => BankFilterAction::Transfer, 'transfer_account_id' => $other->id]);
        $transaction = applyBankFilterRow('GIROCONTO SATISPAY', '-100.00', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::Transferred)
            ->and($transaction->refresh()->transfer_account_id)->toBe($other->id)
            ->and(Expense::query()->count())->toBe(0);
    });
});

test('ignores', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay(['action' => BankFilterAction::Ignore]);
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::Ignored)
            ->and($transaction->refresh()->ignored_at)->not->toBeNull()
            ->and(Expense::query()->count())->toBe(0);
    });
});

test('writes which filter did it', function (BankFilterAction $action): void {
    insideBianchi(function () use ($action): void {
        $filter = $action === BankFilterAction::Pay ? applyBankFilterLuce() : applyBankFilterSatispay(['action' => $action]);
        $transaction = applyBankFilterRow('ADDEBITO SDD EDISON ENERGIA SATISPAY', '-72.40', '2026-11-05');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->not->toBe(BankFilterOutcome::None)
            ->and($transaction->refresh()->bank_filter_id)->toBe($filter->id);
    });
})->with([BankFilterAction::Pay, BankFilterAction::Create, BankFilterAction::Ignore]);

test('does nothing on a declined transaction', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay();
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24', ['declined_at' => '2026-11-25 08:00:00']);

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::None)
            ->and(Expense::query()->count())->toBe(0)
            ->and($transaction->refresh()->bank_filter_id)->toBeNull();
    });
});

test('stays to sort when the planned expense has no due date to pay', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterLuce(['start_date' => '2027-01-05']);
        $transaction = applyBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::None)
            ->and(Expense::query()->count())->toBe(0)
            ->and($transaction->refresh()->expense_id)->toBeNull()
            ->and($transaction->bank_filter_id)->toBeNull();
    });
});

test('stays to sort when the planned expense of the filter ended before the day', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterLuce(['end_date' => '2026-09-30']);
        $transaction = applyBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        expect(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::None)
            ->and(Expense::query()->count())->toBe(0);
    });
});

test('a filter whose category was deleted still creates the expense, without a category', function (): void {
    insideBianchi(function (): void {
        $category = ExpenseCategory::factory()->create(['family_id' => applyBankFilterFamily()]);
        $filter = applyBankFilterSatispay(['category_id' => $category->id]);
        $category->delete();
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');

        // The filter in hand still says the category: it was read before the category went away
        expect($filter->category_id)->toBe($category->id)
            ->and(resolve(ApplyBankFilter::class)->handle($transaction, $filter))->toBe(BankFilterOutcome::Created)
            ->and(Expense::query()->sole()->category_id)->toBeNull();
    });
});

test('two calls at once settle it once', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay();
        $transaction = applyBankFilterRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');
        $apply = resolve(ApplyBankFilter::class);

        // The second call holds the same row as read before the first: it finds the transaction no longer to sort
        $stale = BankTransaction::query()->findOrFail($transaction->id);

        expect($apply->handle($transaction, $filter))->toBe(BankFilterOutcome::Created)
            ->and($apply->handle($stale, $filter))->toBe(BankFilterOutcome::None)
            ->and(Expense::query()->count())->toBe(1);
    });
});

test('a commission is never taken by a filter by itself', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay(['action' => BankFilterAction::Ignore]);
        $fee = applyBankFilterRow('COMMISSIONI DOMICILIAZIONE SATISPAY EUROPE', '-0.40', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($fee, $filter))->toBe(BankFilterOutcome::None)
            ->and($fee->refresh()->ignored_at)->toBeNull();
    });
});

test('a filter that recognises its commission with words joins it to the expense it creates', function (): void {
    insideBianchi(function (): void {
        $filter = applyBankFilterSatispay(['fee_mode' => BankFilterFee::Words, 'fee_contains' => ['COMMISSIONI', 'SATISPAY'], 'excludes' => ['COMMISSIONI']]);
        $fee = applyBankFilterRow('COMMISSIONI SATISPAY', '-0.40', '2026-11-25');
        // Too far, and bigger than the debit: neither is its commission
        applyBankFilterRow('COMMISSIONI SATISPAY', '-0.30', '2026-11-30');
        applyBankFilterRow('COMMISSIONI SATISPAY', '-12.00', '2026-11-24');
        $debit = applyBankFilterRow('RICARICA SATISPAY', '-10.00', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($debit, $filter))->toBe(BankFilterOutcome::Created);

        $expense = Expense::query()->sole();
        expect($expense->amount)->toBe('10.40')
            ->and($expense->fee_amount)->toBe('0.40')
            ->and($fee->refresh()->expense_id)->toBe($expense->id)
            ->and($fee->matched_by)->toBe(BankMatchSource::Fee);
    });
});

test('a filter without a commission leaves the commission of the mandate to sort', function (): void {
    insideBianchi(function (): void {
        $text = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
        $filter = applyBankFilterSatispay(['fee_mode' => BankFilterFee::None, 'excludes' => ['COMMISSIONI']]);
        $fee = applyBankFilterRow('COMMISSIONI '.$text, '-0.40', '2026-11-25');
        $debit = applyBankFilterRow($text, '-10.00', '2026-11-24');

        expect(resolve(ApplyBankFilter::class)->handle($debit, $filter))->toBe(BankFilterOutcome::Created)
            ->and(Expense::query()->sole()->amount)->toBe('10.00')
            ->and($fee->refresh()->expense_id)->toBeNull();
    });
});
