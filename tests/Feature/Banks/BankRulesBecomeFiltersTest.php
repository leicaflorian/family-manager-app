<?php

declare(strict_types=1);

use App\Models\BankConnection;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Illuminate\Support\Facades\DB;

/**
 * Runs the data migration.
 */
function bankRulesBecomeFiltersRun(): void
{
    $migration = require database_path('migrations/2026_11_27_000003_turn_bank_rules_into_filters.php');
    $migration->up();
}

/**
 * A family with an account linked to a bank.
 *
 * @return array{Family, FinancialAccount}
 */
function bankRulesBecomeFiltersFamily(): array
{
    $family = Family::factory()->create();
    $account = FinancialAccount::factory()->for($family)->create(['bank_connection_id' => BankConnection::factory()->create(['family_id' => $family->id])->id]);

    return [$family, $account];
}

test('a rule of words becomes a filter with those words', function (): void {
    [$family, $account] = bankRulesBecomeFiltersFamily();
    $planned = PlannedExpense::factory()->create(['family_id' => $family->id, 'title' => 'Luce', 'financial_account_id' => $account->id, 'bank_match' => 'EDISON.*ENERGIA']);

    bankRulesBecomeFiltersRun();

    $row = DB::table('bank_filters')->where('planned_expense_id', $planned->id)->sole();
    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Luce')
        ->and($row->action)->toBe('pay')
        ->and($row->direction)->toBe('expense')
        ->and($row->fee_mode)->toBe('auto')
        ->and($row->pattern)->toBeNull()
        ->and($row->financial_account_id)->toBe($account->id)
        ->and($row->family_id)->toBe($family->id)
        ->and(json_decode($row->contains, true))->toBe(['EDISON', 'ENERGIA'])
        ->and(DB::table('planned_expenses')->where('id', $planned->id)->value('bank_match'))->toBe('EDISON.*ENERGIA');
});

test('another rule keeps its expression', function (): void {
    [$family] = bankRulesBecomeFiltersFamily();
    $advanced = PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => 'EDISON|ENEL']);
    $short = PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => 'AB.*ENERGIA']);

    bankRulesBecomeFiltersRun();

    foreach ([$advanced, $short] as $planned) {
        $row = DB::table('bank_filters')->where('planned_expense_id', $planned->id)->sole();
        expect($row->pattern)->toBe($planned->bank_match)
            ->and(json_decode($row->contains, true))->toBe([]);
    }
});

test('running it twice makes one filter', function (): void {
    [$family] = bankRulesBecomeFiltersFamily();
    $planned = PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => 'EDISON']);

    bankRulesBecomeFiltersRun();
    bankRulesBecomeFiltersRun();

    expect(DB::table('bank_filters')->where('planned_expense_id', $planned->id)->count())->toBe(1);
});

test("a planned income's rule becomes an income filter", function (): void {
    [$family] = bankRulesBecomeFiltersFamily();
    $planned = PlannedIncome::factory()->create(['family_id' => $family->id, 'title' => 'Stipendio', 'bank_match' => 'ACME.*STIPENDIO']);

    bankRulesBecomeFiltersRun();

    $row = DB::table('bank_filters')->where('planned_income_id', $planned->id)->sole();
    expect($row->direction)->toBe('income')
        ->and($row->planned_expense_id)->toBeNull()
        ->and($row->name)->toBe('Stipendio');
});

test('an empty rule makes no filter', function (): void {
    [$family] = bankRulesBecomeFiltersFamily();
    PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => null]);
    PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => '']);

    bankRulesBecomeFiltersRun();

    expect(DB::table('bank_filters')->count())->toBe(0);
});

test('a rule with a regex escape of a letter or digit keeps its expression', function (string $rule): void {
    [$family] = bankRulesBecomeFiltersFamily();
    $planned = PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => $rule]);

    bankRulesBecomeFiltersRun();

    $row = DB::table('bank_filters')->where('planned_expense_id', $planned->id)->sole();
    expect($row->pattern)->toBe($rule)->and(json_decode($row->contains, true))->toBe([]);
})->with(['FATTURA\d.*EDISON', '\bEDISON\b', 'A B']);

test('an escaped dot or space in a word is unescaped to the literal text', function (): void {
    [$family] = bankRulesBecomeFiltersFamily();
    $planned = PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => 'ACME\.*S\.P\.A']);
    $quoted = PlannedExpense::factory()->create(['family_id' => $family->id, 'bank_match' => preg_quote('ACME S.P.A', '~').'.*ENERGIA']);

    bankRulesBecomeFiltersRun();

    expect(json_decode((string) DB::table('bank_filters')->where('planned_expense_id', $quoted->id)->sole()->contains, true))->toBe(['ACME S.P.A', 'ENERGIA'])
        ->and(DB::table('bank_filters')->where('planned_expense_id', $planned->id)->sole()->pattern)->toBe('ACME\.*S\.P\.A');
});

test('an item without an account reads every account, whatever the default is', function (): void {
    [$family, $account] = bankRulesBecomeFiltersFamily();
    $account->update(['is_default' => true]);
    FinancialAccount::factory()->for($family)->create(['bank_connection_id' => $account->bank_connection_id]);
    $planned = PlannedExpense::factory()->create(['family_id' => $family->id, 'financial_account_id' => null, 'bank_match' => 'EDISON']);

    bankRulesBecomeFiltersRun();

    expect(DB::table('bank_filters')->where('planned_expense_id', $planned->id)->sole()->financial_account_id)->toBeNull();
});
