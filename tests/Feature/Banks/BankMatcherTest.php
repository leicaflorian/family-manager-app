<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Services\Banks\BankMatcher;

beforeEach(function (): void {
    registerFamily();
});

/**
 * A planned expense of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function bankMatcherPlanned(string $title, string $rule, float $amount, string $start, array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => $title,
        'bank_match' => $rule,
        'amount' => $amount,
        'start_date' => $start,
        ...$attributes,
    ]);
}

/**
 * A booked transaction of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function bankMatcherTransaction(string $text, string $amount, string $day, array $attributes = []): BankTransaction
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

test('the Luce rule picks Luce and its due date of 05/11/2026', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-01-05');
        bankMatcherPlanned('Gas', 'ENI PLENITUDE', 60, '2026-01-10');
        $transaction = bankMatcherTransaction('ADDEBITO SDD EDISON ENERGIA SPA', '-84.20', '2026-11-06');
        $matcher = resolve(BankMatcher::class);

        $planned = $matcher->planned($transaction);
        $deadline = $matcher->deadline($transaction, $planned);

        expect($planned?->is($luce))->toBeTrue()
            ->and($deadline?->occurrence->originalDate->toDateString())->toBe('2026-11-05');
    });
});

test('an income never matches a planned expense and the other way round', function (): void {
    insideBianchi(function (): void {
        $expense = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-01-05');
        $income = PlannedIncome::factory()->create([
            'family_id' => $expense->family_id, 'bank_match' => 'EDISON', 'amount' => 84.20, 'start_date' => '2026-01-05',
        ]);
        $matcher = resolve(BankMatcher::class);

        expect($matcher->planned(bankMatcherTransaction('EDISON', '-84.20', '2026-11-05'))?->is($expense))->toBeTrue()
            ->and($matcher->planned(bankMatcherTransaction('EDISON', '84.20', '2026-11-05'))?->is($income))->toBeTrue();

        $income->delete();
        expect($matcher->planned(bankMatcherTransaction('EDISON', '84.20', '2026-11-05')))->toBeNull();
    });
});

test('a transaction in another currency matches nothing', function (): void {
    insideBianchi(function (): void {
        bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-01-05');

        expect(resolve(BankMatcher::class)->planned(bankMatcherTransaction('EDISON', '-84.20', '2026-11-05', ['currency' => 'USD'])))->toBeNull();
    });
});

test('a rule tied to another account does not match', function (): void {
    insideBianchi(function (): void {
        $other = FinancialAccount::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]);
        $transaction = bankMatcherTransaction('EDISON', '-84.20', '2026-11-05');
        $tied = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-01-05', ['financial_account_id' => $other->id]);
        $matcher = resolve(BankMatcher::class);

        expect($matcher->planned($transaction))->toBeNull();

        $tied->update(['financial_account_id' => $transaction->financial_account_id]);
        expect($matcher->planned($transaction)?->is($tied))->toBeTrue();
    });
});

test('a planned expense that ended before the day does not match', function (): void {
    insideBianchi(function (): void {
        bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-01-05', ['end_date' => '2026-10-05']);

        expect(resolve(BankMatcher::class)->planned(bankMatcherTransaction('EDISON', '-84.20', '2026-11-05')))->toBeNull();
    });
});

test('between two planned expenses the closest amount wins, a tie picks none', function (): void {
    insideBianchi(function (): void {
        $small = bankMatcherPlanned('Affitto', 'ZAMBONI', 700, '2026-01-01', ['fee_amount' => 2]);
        $big = bankMatcherPlanned('Affitto box', 'ZAMBONI', 800, '2026-01-01');
        $matcher = resolve(BankMatcher::class);

        expect($matcher->planned(bankMatcherTransaction('ZAMBONI SRL', '-702.00', '2026-11-02'))?->is($small))->toBeTrue()
            ->and($matcher->planned(bankMatcherTransaction('ZAMBONI SRL', '-790.00', '2026-11-02'))?->is($big))->toBeTrue()
            ->and($matcher->planned(bankMatcherTransaction('ZAMBONI SRL', '-751.00', '2026-11-02')))->toBeNull();
    });
});

test('a monthly due date 16 days away is not picked, the oldest late one is', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-10-05', ['end_date' => '2026-11-30']);
        $matcher = resolve(BankMatcher::class);

        // 05/11 is 15 days back: within half a month
        expect($matcher->deadline(bankMatcherTransaction('EDISON', '-84.20', '2026-11-20'), $luce)?->occurrence->originalDate->toDateString())->toBe('2026-11-05')
            // 16 days: out of reach, so the oldest late one, 05/10
            ->and($matcher->deadline(bankMatcherTransaction('EDISON', '-84.20', '2026-11-21'), $luce)?->occurrence->originalDate->toDateString())->toBe('2026-10-05');
    });
});

test('a yearly policy matches within 45 days', function (): void {
    insideBianchi(function (): void {
        $policy = bankMatcherPlanned('Assicurazione auto', 'UNIPOL', 418, '2025-12-20', ['interval_unit' => IntervalUnit::Year]);
        $matcher = resolve(BankMatcher::class);

        // 20/12/2026 is 45 days after 05/11
        expect($matcher->deadline(bankMatcherTransaction('UNIPOL', '-418.00', '2026-11-05'), $policy)?->occurrence->originalDate->toDateString())->toBe('2026-12-20');

        $policy->update(['start_date' => '2025-12-21']);
        $policy->refresh();
        $none = $matcher->deadline(bankMatcherTransaction('UNIPOL', '-418.00', '2026-11-05'), $policy);

        // 46 days away: not within reach, and the one of 2025 is old but late: it is picked as the oldest late one
        expect($none?->occurrence->originalDate->toDateString())->toBe('2025-12-21');
    });
});

test('a skipped due date is never picked', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-11-05', ['end_date' => '2026-11-30']);
        PlannedExpenseException::factory()->create([
            'family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-05', 'type' => ExceptionType::Skip,
        ]);

        expect(resolve(BankMatcher::class)->deadline(bankMatcherTransaction('EDISON', '-84.20', '2026-11-05'), $luce))->toBeNull();
    });
});

test('the closest due date within reach is given even when paid, never an older open month', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-01-05');
        $byHand = Expense::factory()->create(['family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-05', 'date' => '2026-11-05', 'amount' => 84.0]);

        $deadline = resolve(BankMatcher::class)->deadline(bankMatcherTransaction('EDISON', '-84.20', '2026-11-06'), $luce);

        expect($deadline?->occurrence->originalDate->toDateString())->toBe('2026-11-05')
            ->and($deadline?->payment?->is($byHand))->toBeTrue();
    });
});

test('no due date within reach and none late gives none', function (): void {
    insideBianchi(function (): void {
        $luce = bankMatcherPlanned('Luce', 'EDISON', 84.20, '2026-11-05', ['end_date' => '2026-11-30']);
        Expense::factory()->create(['family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-05', 'date' => '2026-11-05']);

        expect(resolve(BankMatcher::class)->deadline(bankMatcherTransaction('EDISON', '-84.20', '2026-11-25'), $luce))->toBeNull();
    });
});

test('a one-off planned expense is picked at any distance', function (): void {
    insideBianchi(function (): void {
        $tax = bankMatcherPlanned('Bollo', 'ACI', 200, '2026-03-01', ['is_recurring' => false]);

        expect(resolve(BankMatcher::class)->deadline(bankMatcherTransaction('ACI BOLLO', '-200.00', '2026-11-20'), $tax)?->occurrence->originalDate->toDateString())->toBe('2026-03-01');
    });
});
